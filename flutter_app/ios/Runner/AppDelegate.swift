import Flutter
import UIKit

@main
@objc class AppDelegate: FlutterAppDelegate, FlutterImplicitEngineDelegate {
  override func application(
    _ application: UIApplication,
    didFinishLaunchingWithOptions launchOptions: [UIApplication.LaunchOptionsKey: Any]?
  ) -> Bool {
    if #available(iOS 16.0, *) { _ = PttReceiver.shared }
    return super.application(application, didFinishLaunchingWithOptions: launchOptions)
  }

  func didInitializeImplicitFlutterEngine(_ engineBridge: FlutterImplicitEngineBridge) {
    GeneratedPluginRegistrant.register(with: engineBridge.pluginRegistry)
    guard let registrar = engineBridge.pluginRegistry.registrar(forPlugin: "PttReceiver") else { return }
    FlutterMethodChannel(name:"toporganic/ptt_receiver",binaryMessenger:registrar.messenger()).setMethodCallHandler { call,result in
      guard #available(iOS 16.0, *) else {
        if call.method == "state" { result(["enabled":false,"error":"الاستقبال بالخلفية يتطلب iOS 16 أو أحدث"]);return }
        if call.method == "disable" || call.method == "pause" { result(true);return }
        result(FlutterError(code:"ptt_ios_version",message:"الاستقبال بالخلفية يتطلب iOS 16 أو أحدث",details:nil));return }
      let receiver=PttReceiver.shared
      let args=call.arguments as? [String:Any] ?? [:]
      switch call.method {
      case "enable": receiver.enable(base:args["base"] as? String ?? "",token:args["token"] as? String ?? "",user:args["user"] as? String ?? "") { ok,error in
        if ok { result(true) } else { result(FlutterError(code:"ptt_enable",message:error,details:nil)) }
      }
      case "disable": receiver.disable();result(true)
      case "pause": receiver.pause(args["paused"] as? Bool ?? false);result(true)
      case "state": result(["enabled":receiver.enabled,"speaker":receiver.speaker,"error":receiver.error])
      default:result(FlutterMethodNotImplemented)
      }
    }
  }
}
