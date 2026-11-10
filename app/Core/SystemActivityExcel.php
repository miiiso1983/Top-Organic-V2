<?php
declare(strict_types=1);
namespace App\Core;

/** Streaming XLSX export: bounded PHP memory, inline text cells and no spreadsheet formulas. */
final class SystemActivityExcel
{
    private const NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    private const HEADERS = ['رقم الحدث','التاريخ والوقت — بغداد','منفّذ الإجراء','الفرع','نوع السجل','العملية','السجل المتأثر','رقم السجل المتأثر','اسم الموظف المتأثر','النتيجة','تفاصيل الحدث'];

    public static function filterDescription(array $f): string
    {
        $items=['من: '.($f['from']?:'البداية').' — إلى: '.($f['to']?:'الآن')];
        if($f['q'])$items[]='البحث: '.$f['q'];
        if($f['source'])$items[]='نوع السجل: '.SystemActivityReport::SOURCES[$f['source']];
        if($f['result'])$items[]='النتيجة: '.SystemActivityReport::RESULTS[$f['result']];
        if($f['action'])$items[]='العملية: '.SystemActivityPresentation::action($f['action']);
        if($f['entity'])$items[]='السجل المتأثر: '.SystemActivityPresentation::entity($f['entity']);
        if($f['entity_id'])$items[]='رقم السجل المتأثر: '.$f['entity_id'];
        if($f['user_id'])$items[]='منفّذ الإجراء: '.(Database::query('SELECT full_name FROM users WHERE id=?',[$f['user_id']])->fetchColumn()?:'مستخدم رقم '.$f['user_id']);
        if($f['branch_id'])$items[]='الفرع: '.(Database::query('SELECT name FROM branches WHERE id=?',[$f['branch_id']])->fetchColumn()?:'فرع رقم '.$f['branch_id']);
        return implode(' | ',$items);
    }
    private static function text(string $value): string
    {
        return preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u','',mb_convert_encoding($value,'UTF-8','UTF-8'));
    }
    private static function row(\XMLWriter $xml, int $number, array $values, int $style=0): void
    {
        $xml->startElement('row');$xml->writeAttribute('r',(string)$number);
        if($number===1 || $number===3){$xml->writeAttribute('ht','28');$xml->writeAttribute('customHeight','1');}
        elseif($number===2){$xml->writeAttribute('ht','56');$xml->writeAttribute('customHeight','1');}
        foreach(array_values($values) as $index=>$value){
            $xml->startElement('c');$xml->writeAttribute('r',chr(65+$index).$number);$xml->writeAttribute('t','inlineStr');$xml->writeAttribute('s',(string)$style);
            $xml->startElement('is');$xml->startElement('t');$xml->writeAttribute('xml:space','preserve');$xml->text(self::text((string)($value??'')));$xml->endElement();$xml->endElement();$xml->endElement();
        }
        $xml->endElement();
    }
    /** The caller owns and must delete the returned temporary XLSX. */
    public static function build(iterable $events, array $filters): string
    {
        $summary=self::filterDescription($filters);
        $dir=sys_get_temp_dir().'/activity-export-'.bin2hex(random_bytes(12));
        if(!mkdir($dir,0700))throw new \RuntimeException('Temporary directory unavailable');
        $output=$dir.'.xlsx';touch($output);chmod($output,0600);$zip=new \ZipArchive();$xml=null;$open=false;
        try {
            if($zip->open($output,\ZipArchive::CREATE|\ZipArchive::OVERWRITE)!==true)throw new \RuntimeException('Archive unavailable');
            chmod($output,0600);$open=true;$sheet=0;$row=0;
            $start=function()use(&$xml,&$sheet,&$row,$dir,$summary){
                $sheet++;$row=3;$xml=new \XMLWriter();$xml->openUri($dir.'/sheet'.$sheet.'.xml');$xml->startDocument('1.0','UTF-8');$xml->startElementNS(null,'worksheet',self::NS);
                $xml->startElement('sheetViews');$xml->startElement('sheetView');$xml->writeAttribute('workbookViewId','0');$xml->writeAttribute('rightToLeft','1');$xml->startElement('pane');$xml->writeAttribute('ySplit','3');$xml->writeAttribute('topLeftCell','A4');$xml->writeAttribute('activePane','bottomLeft');$xml->writeAttribute('state','frozen');$xml->endElement();$xml->endElement();$xml->endElement();
                $xml->startElement('cols');foreach([12,23,26,20,24,38,32,18,26,23,90] as $i=>$width){$xml->startElement('col');$xml->writeAttribute('min',(string)($i+1));$xml->writeAttribute('max',(string)($i+1));$xml->writeAttribute('width',(string)$width);$xml->writeAttribute('customWidth','1');$xml->endElement();}$xml->endElement();$xml->startElement('sheetData');
                self::row($xml,1,['سجل أحداث النظام — جميع النتائج المطابقة للفلاتر'],1);
                self::row($xml,2,[$summary],0);self::row($xml,3,self::HEADERS,1);
            };
            $finish=function()use(&$xml,&$row,&$sheet,$dir,$zip){
                $xml->endElement();$xml->startElement('autoFilter');$xml->writeAttribute('ref','A3:K'.$row);$xml->endElement();$xml->startElement('mergeCells');$xml->writeAttribute('count','2');foreach(['A1:K1','A2:K2'] as $range){$xml->startElement('mergeCell');$xml->writeAttribute('ref',$range);$xml->endElement();}$xml->endElement();$xml->endElement();$xml->endDocument();$xml->flush();$xml=null;
                if(!$zip->addFile($dir.'/sheet'.$sheet.'.xml','xl/worksheets/sheet'.$sheet.'.xml'))throw new \RuntimeException('Worksheet could not be added');
            };
            $start();
            foreach($events as $e){
                $values=[$e['id'],$e['created_at'],$e['user_name'],$e['branch_name']?:'غير محدد',SystemActivityReport::SOURCES[$e['source']],SystemActivityPresentation::action($e['action']),SystemActivityPresentation::entity($e['entity']),$e['entity_id']?:'',$e['target_name']??'',SystemActivityReport::RESULTS[$e['result']]??'غير محدد',SystemActivityPresentation::details($e)];
                // Excel limits a text cell to 32,767 UTF-16 code units. Split very long details into labelled continuation rows, never truncate evidence.
                $parts=[];$segments=1;foreach($values as $i=>$v){$parts[$i]=mb_str_split((string)$v,15000,'UTF-8')?:[''];$segments=max($segments,count($parts[$i]));}
                for($n=0;$n<$segments;$n++){
                    if($row===1048576){$finish();$start();}
                    $cells=[];foreach($parts as $i=>$chunks)$cells[$i]=$chunks[$n]??($n>0&&$i<2?$chunks[0]:'');
                    if($n>0)$cells[5]='تكملة تفاصيل الحدث ('.($n+1).')';
                    self::row($xml,++$row,$cells);
                }
            }
            $finish();
            $contentTypes='<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
            $workbook='<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="'.self::NS.'" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><bookViews><workbookView/></bookViews><sheets>';
            $rels='<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="styles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
            for($i=1;$i<=$sheet;$i++){
                $contentTypes.='<Override PartName="/xl/worksheets/sheet'.$i.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
                $workbook.='<sheet name="سجل الأحداث '.$i.'" sheetId="'.$i.'" r:id="sheet'.$i.'"/>';
                $rels.='<Relationship Id="sheet'.$i.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$i.'.xml"/>';
            }
            $zip->addFromString('[Content_Types].xml',$contentTypes.'</Types>');$zip->addFromString('xl/workbook.xml',$workbook.'</sheets></workbook>');$zip->addFromString('xl/_rels/workbook.xml.rels',$rels.'</Relationships>');
            $zip->addFromString('_rels/.rels','<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="workbook" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
            $zip->addFromString('xl/styles.xml','<?xml version="1.0" encoding="UTF-8"?><styleSheet xmlns="'.self::NS.'"><fonts count="2"><font><sz val="11"/><name val="Arial"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Arial"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF216C37"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="right" vertical="top" wrapText="1"/></xf><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="right" vertical="center" wrapText="1"/></xf></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>');
            if(!$zip->close())throw new \RuntimeException('Archive save failed');$open=false;return $output;
        } catch(\Throwable $e){if($open)$zip->close();if(is_file($output))unlink($output);throw $e;}
        finally {$xml=null;foreach(glob($dir.'/*')?:[] as $file)unlink($file);rmdir($dir);}
    }
}
