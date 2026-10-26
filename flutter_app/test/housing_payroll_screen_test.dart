import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:top_organic_app/core/network/api_client.dart';
import 'package:top_organic_app/core/locale/locale_provider.dart';
import 'package:top_organic_app/features/housing/housing_screen.dart';
import 'package:top_organic_app/features/hr/salary_detail_screen.dart';
class Fake extends ApiClient {
  Map<String,dynamic>? query;
  @override Future<Response> get(String path,{Map<String,dynamic>? query}) async {
    this.query=query;
    final columns=[{'key':'base','label':'الراتب الأساسي','value':1040000,'section':'earnings','unit':'money'},{'key':'insurance','label':'التأمينات المستقطعة','value':12345,'section':'deductions','unit':'money'},{'key':'net','label':'صافي','value':900000,'section':'summary','unit':'money'}];
    final data=path.contains('housing')?{'data':[
      {'id':1,'full_name':'موظف الصالة المقيم ذو الاسم الطويل','department_name':'الصالة','site_name':'السكن الداخلي','can_edit':false},
      {'id':2,'full_name':'موظف الحراسة','department_name':'الحراسة','site_name':'السكن الداخلي','can_edit':true},
    ]}:path.startsWith('/hr-team')?{'data':{'payroll':{'total_earnings':1040000,'total_deductions':140000},'columns':columns}}:{'data':{'total_earnings':1040000,'total_deductions':140000},'columns':columns};
    return Response(requestOptions:RequestOptions(path:path),data:{'ok':true,...data});
  }
}
Widget host(Widget screen, Fake api,TargetPlatform platform)=>MultiProvider(providers:[Provider<ApiClient>.value(value:api),ChangeNotifierProvider(create:(_)=>LocaleProvider())],child:MaterialApp(theme:ThemeData(platform:platform),builder:(c,w)=>MediaQuery(data:MediaQuery.of(c).copyWith(textScaler:TextScaler.linear(1.8)),child:Directionality(textDirection:TextDirection.rtl,child:w!)),home:screen));
void main(){
 for(final platform in [TargetPlatform.android,TargetPlatform.iOS]){
  testWidgets('Housing all residents and edit scope $platform',(t)async{
   t.view.physicalSize=const Size(390,850);t.view.devicePixelRatio=1;addTearDown(t.view.resetPhysicalSize);addTearDown(t.view.resetDevicePixelRatio);
   final api=Fake();await t.pumpWidget(host(const HousingScreen(),api,platform));await t.pumpAndSettle();
   expect(find.text('موظف الصالة المقيم ذو الاسم الطويل'),findsOneWidget);expect(find.byIcon(Icons.edit_outlined),findsOneWidget);expect(api.query?['residents_only'],'1');expect(t.takeException(),isNull);
   await t.tap(find.byType(Switch));await t.pumpAndSettle();expect(api.query?['residents_only'],'0');
  });
  for(final eid in [null,1])testWidgets('Web payroll columns $platform $eid',(t)async{
   t.view.physicalSize=const Size(320,850);t.view.devicePixelRatio=1;addTearDown(t.view.resetPhysicalSize);addTearDown(t.view.resetDevicePixelRatio);
   await t.pumpWidget(host(SalaryDetailScreen(payrollId:1,period:'2026-10',employeeId:eid),Fake(),platform));await t.pumpAndSettle();
   expect(find.text('الراتب الأساسي'),findsOneWidget);expect(find.text('1,040,000'),findsOneWidget);expect(find.text('التأمينات المستقطعة'),findsOneWidget);expect(find.text('12,345'),findsOneWidget);expect(t.takeException(),isNull);
  });
 }
}
