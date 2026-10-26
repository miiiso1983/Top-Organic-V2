<?php
namespace App\Core;
/** Freeze approvers of already-submitted requests when their employee changes manager. */
final class HrTransferSnapshot
{
    public static function key(array $config): string { ksort($config);return hash('sha256',json_encode($config)); }
    public static function get(int $request,string $key): ?array {
        if(!$request)return null;
        try{$value=Database::query('SELECT user_ids FROM hr_transfer_approvers WHERE request_id=? AND resolver_key=?',[$request,$key])->fetchColumn();}
        catch(\PDOException $e){if(($e->errorInfo[1]??0)===1146)return null;throw $e;}
        return $value===false?null:array_map('intval',json_decode($value,true));
    }
    public static function save(int $request,string $key,array $ids): void { Database::query('INSERT IGNORE INTO hr_transfer_approvers(request_id,resolver_key,user_ids) VALUES (?,?,?)',[$request,$key,json_encode(array_values(array_unique(array_map('intval',$ids))))]); }
    public static function capture(int $employeeId): void {
        foreach(Database::query("SELECT id FROM requests WHERE requester_employee_id=? AND status IN ('pending','returned_for_edit') FOR UPDATE",[$employeeId])->fetchAll(\PDO::FETCH_COLUMN) as $id){
            $req=WorkflowEngine::getRequest((int)$id);
            WorkflowEngine::freezeTransferApprovers($req);
            if(!empty($req['workflow_version_id']))foreach(Database::query('SELECT config_json FROM workflow_nodes WHERE version_id=?',[$req['workflow_version_id']])->fetchAll() as $node){
                $config=json_decode($node['config_json'],true)?:[];
                foreach(['approver','fallback'] as $k)if(isset($config[$k])&&is_array($config[$k])){ $c=$config[$k];self::save((int)$id,self::key($c),\App\Core\Workflow\ApproverResolver::resolve($c,$req)); }
            }
        }
    }
}
