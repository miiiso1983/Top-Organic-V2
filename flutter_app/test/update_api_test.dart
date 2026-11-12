import 'dart:convert';
import 'dart:typed_data';
import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'package:top_organic_app/core/network/api_client.dart';
import 'package:top_organic_app/core/storage/auth_storage.dart';
import 'package:top_organic_app/core/updates/update_policy.dart';
class Storage extends AuthStorage { @override Future<String?> getToken()async=>'test'; }
class Adapter implements HttpClientAdapter {
 RequestOptions? last;int status=426;
 @override Future<ResponseBody> fetch(RequestOptions o,Stream<Uint8List>? body,Future<void>? cancel)async{
 last=o;return ResponseBody.fromString(jsonEncode({'error_code':'app_update_required','update':{'platform':'android','minimum_build':69,'version':'1.3.9','enabled':true}}),status,headers:{Headers.contentTypeHeader:['application/json']});
 }
 @override void close({bool force=false}){}
}
void main(){TestWidgetsFlutterBinding.ensureInitialized();test('all API calls send installed build; 426 raises gate without logout or network queue classification',()async{
 debugDefaultTargetPlatformOverride=TargetPlatform.android;
 PackageInfo.setMockInitialValues(appName:'Top Organic',packageName:'com.toporganic.top_organic_app',version:'1.3.8',buildNumber:'68',buildSignature:'test');
 final adapter=Adapter();final dio=Dio()..httpClientAdapter=adapter;final api=ApiClient(authStorage:Storage(),dio:dio);var logout=false;api.onUnauthorized=()=>logout=true;
 try{await api.post('/attendance/check',data:{'type':'in'});fail('Expected 426');}on DioException catch(e){expect(ApiClient.isNetworkError(e),false);}
 expect(adapter.last!.headers['X-App-Build'],'68');expect(adapter.last!.headers['X-App-Platform'],'android');expect(adapter.last!.headers['Authorization'],'Bearer test');expect(UpdateRequiredSignal.policy.value!.minimumBuild,69);expect(logout,false);
 adapter.status=401;try{await api.get('/permissions');}catch(_){}expect(logout,true);
 UpdateRequiredSignal.policy.value=null;debugDefaultTargetPlatformOverride=null;
 });}
