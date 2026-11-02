import Foundation
import UIKit
import AVFoundation
import PushToTalk
import Security

@available(iOS 16.0, *)
final class PttReceiver: NSObject, PTChannelManagerDelegate, PTChannelRestorationDelegate, AVAudioPlayerDelegate, URLSessionTaskDelegate {
    static let shared = PttReceiver()
    private lazy var session = URLSession(configuration:.ephemeral, delegate:self, delegateQueue:nil)
    private var pollTimer: Timer?
    private var cursor: Int?
    private var polling = false
    private var manager: PTChannelManager?
    private var initializationError: String?
    private var credentials: [String: String] = [:]
    private var joinResult: ((Bool, String?) -> Void)?
    private var audioActive = false
    private var paused = false
    private var player: AVAudioPlayer?
    private var queue: [(Int, String)] = []
    private var current: Int?
    private var epoch = 0
    private var seen = Set<Int>()
    private(set) var error = ""
    private(set) var speaker = ""
    var enabled: Bool { manager?.activeChannelUUID != nil && !credentials.isEmpty }
    private var channel: UUID? { credentials["channel"].flatMap(UUID.init(uuidString:)) }
    private let keyQuery: [String: Any] = [kSecClass as String: kSecClassGenericPassword, kSecAttrService as String: "toporganic.ptt.receiver", kSecAttrAccount as String: "session"]

    override private init() {
        super.init()
        var q = keyQuery; q[kSecReturnData as String] = true
        var item: CFTypeRef?
        if SecItemCopyMatching(q as CFDictionary, &item) == errSecSuccess, let data = item as? Data,
           let stored = try? JSONSerialization.jsonObject(with: data) as? [String: String] { credentials = stored }
        NotificationCenter.default.addObserver(self, selector:#selector(foreground),name:UIApplication.didBecomeActiveNotification,object:nil)
        NotificationCenter.default.addObserver(self, selector:#selector(background),name:UIApplication.didEnterBackgroundNotification,object:nil)
        PTChannelManager.channelManager(delegate: self, restorationDelegate: self) { [weak self] manager, failure in
            DispatchQueue.main.async {
                self?.manager = manager
                if let failure { self?.initializationError = failure.localizedDescription; self?.error = failure.localizedDescription; self?.joinResult?(false, self?.error); self?.joinResult = nil }
                else { self?.joinIfRequested() }
            }
        }
    }
    func enable(base: String, token: String, user: String, completion: @escaping (Bool, String?) -> Void) {
        guard base.hasPrefix("https://"), !token.isEmpty, !user.isEmpty else { completion(false,"بيانات الدخول غير متاحة"); return }
        if let initializationError { completion(false,initializationError); return }
        if enabled && credentials["user"] == user { completion(true,error.isEmpty ? nil : error); return }
        if enabled { disable() }
        credentials = ["base":base, "token":token, "user":user, "device":UUID().uuidString, "channel":UUID().uuidString]
        var q=keyQuery; q[kSecValueData as String] = try? JSONSerialization.data(withJSONObject: credentials)
        q[kSecAttrAccessible as String] = kSecAttrAccessibleAfterFirstUnlockThisDeviceOnly
        SecItemDelete(keyQuery as CFDictionary)
        guard SecItemAdd(q as CFDictionary,nil) == errSecSuccess else { credentials=[:]; completion(false,"تعذر حفظ جلسة الاستقبال بأمان"); return }
        error=""; joinResult=completion; joinIfRequested()
    }
    private func joinIfRequested() {
        guard joinResult != nil, let manager, let channel else { return }
        manager.requestJoinChannel(channelUUID: channel, descriptor: PTChannelDescriptor(name:"Top Organic — قنوات العمل",image:nil))
    }
    func disable() {
        let old = credentials
        background(); cursor=nil
        epoch += 1; player?.stop(); player=nil; queue=[]; current=nil; seen=[]; speaker=""; audioActive=false
        if let channel { manager?.leaveChannel(channelUUID:channel) }
        credentials=[:]; SecItemDelete(keyQuery as CFDictionary)
        send(path:"/ptt/receiver/remove", method:"POST", json:["device_id":old["device"] ?? ""], auth:old) { _,_ in }
    }
    func pause(_ value: Bool) {
        paused=value
        if value { player?.stop(); finish(played:false) }
        else { activateNext() }
    }
    private func activateNext() {
        guard !paused else { return }
        if current==nil, let first=queue.first, let channel { manager?.setActiveRemoteParticipant(PTParticipant(name:first.1,image:nil),channelUUID:channel) }
        next()
    }
    func channelDescriptor(restoredChannelUUID channelUUID: UUID) -> PTChannelDescriptor { PTChannelDescriptor(name:"Top Organic — قنوات العمل",image:nil) }
    func channelManager(_ channelManager: PTChannelManager, didJoinChannel channelUUID: UUID, reason: PTChannelJoinReason) {
        guard channelUUID == channel else { channelManager.leaveChannel(channelUUID:channelUUID); return }
        channelManager.setTransmissionMode(.listenOnly, channelUUID:channelUUID)
        foreground()
        joinResult?(true,nil); joinResult=nil
    }
    func channelManager(_ channelManager: PTChannelManager, failedToJoinChannel channelUUID: UUID, error: Error) {
        self.error=error.localizedDescription; joinResult?(false,self.error); joinResult=nil
        credentials=[:]; SecItemDelete(keyQuery as CFDictionary)
    }
    func channelManager(_ channelManager: PTChannelManager, didLeaveChannel channelUUID: UUID, reason: PTChannelLeaveReason) {
        if channelUUID == channel { disable() }
    }
    func channelManager(_ channelManager: PTChannelManager, receivedEphemeralPushToken pushToken: Data) {
        let auth=credentials
        #if DEBUG
        let environment="development"
        #else
        let environment="production"
        #endif
        send(path:"/ptt/receiver/register",method:"POST",json:["device_id":auth["device"] ?? "","token":pushToken.map{String(format:"%02x",$0)}.joined(),"environment":environment],auth:auth) { [weak self] data,status in
            DispatchQueue.main.async {
                guard self?.credentials["device"] == auth["device"] else { return }
                let result = data.flatMap { try? JSONSerialization.jsonObject(with:$0) as? [String:Any] }
                if status != 200 || result?["background_ready"] as? Bool != true { self?.error="استقبال الخلفية ينتظر إعداد APNs في السيرفر" }
            }
        }
    }
    func incomingPushResult(channelManager: PTChannelManager, channelUUID: UUID, pushPayload: [String: Any]) -> PTPushResult {
        guard channelUUID == channel, String(describing:pushPayload["recipient_user_id"] ?? "") == credentials["user"],
              let id=pushPayload["message_id"] as? Int else { return .leaveChannel }
        let name=pushPayload["sender_name"] as? String ?? "رسالة صوتية"
        if !seen.contains(id) && current != id && !queue.contains(where:{$0.0==id}) { queue.append((id,name)) }
        DispatchQueue.main.async { [weak self] in self?.next() }
        return .activeRemoteParticipant(PTParticipant(name:name,image:nil))
    }
    func channelManager(_ channelManager: PTChannelManager, didActivate audioSession: AVAudioSession) {
        audioActive=true
        try? audioSession.setCategory(.playback,mode:.spokenAudio)
        next()
    }
    func channelManager(_ channelManager: PTChannelManager, didDeactivate audioSession: AVAudioSession) {
        audioActive=false; player?.stop(); finish(played:false)
    }
    func channelManager(_ channelManager: PTChannelManager, channelUUID: UUID, didBeginTransmittingFrom source: PTChannelTransmitRequestSource) { channelManager.stopTransmitting(channelUUID:channelUUID) }
    func channelManager(_ channelManager: PTChannelManager, channelUUID: UUID, didEndTransmittingFrom source: PTChannelTransmitRequestSource) {}
    private func next() {
        guard audioActive, !paused, current==nil, !queue.isEmpty, enabled else { return }
        let (id,name)=queue.removeFirst();current=id;speaker=name
        let version=epoch;let auth=credentials
        send(path:"/ptt/messages/\(id)/audio",auth:auth) { [weak self] data,status in
            DispatchQueue.main.async {
                guard let self, self.epoch==version, self.current==id else { return }
                guard status==200, let data, data.count<=16*1024*1024, self.audioActive, !self.paused else { self.finish(played:false); return }
                do { let player=try AVAudioPlayer(data:data);self.player=player;player.delegate=self
                    if !player.play() { self.finish(played:false) }
                } catch { self.error="تعذر تشغيل الرسالة الصوتية";self.finish(played:false) }
            }
        }
    }
    func audioPlayerDidFinishPlaying(_ player: AVAudioPlayer, successfully flag: Bool) { finish(played:flag) }
    func audioPlayerDecodeErrorDidOccur(_ player: AVAudioPlayer, error: Error?) { finish(played:false) }
    private func finish(played: Bool) {
        if let id=current, played { seen.insert(id); if seen.count>500 { seen=[id] };send(path:"/ptt/messages/\(id)/played",method:"POST",auth:credentials){_,_ in} }
        player=nil;current=nil;speaker=""
        if queue.isEmpty || paused, let channel { manager?.setActiveRemoteParticipant(nil,channelUUID:channel) }
        else if audioActive && !paused { next() }
    }
    @objc private func foreground() {
        guard enabled, UIApplication.shared.applicationState == .active else { return }
        pollTimer?.invalidate(); pollTimer=Timer.scheduledTimer(withTimeInterval:3,repeats:true){[weak self] _ in self?.poll()};poll()
    }
    @objc private func background() { pollTimer?.invalidate();pollTimer=nil }
    private func poll() {
        guard enabled, !polling else { return };polling=true
        let auth=credentials;let version=epoch
        send(path:"/ptt/delivery"+(cursor.map{"?after=\($0)"} ?? ""),auth:auth){[weak self] data,status in
            DispatchQueue.main.async {
                guard let self else { return };self.polling=false
                guard self.epoch==version, self.enabled else { return }
                if status==401 || status==403 { self.disable();return }
                guard let data,let body=try? JSONSerialization.jsonObject(with:data) as? [String:Any],let result=body["data"] as? [String:Any] else { return }
                self.cursor=result["cursor"] as? Int
                for row in result["messages"] as? [[String:Any]] ?? [] {
                    guard let id=Int(String(describing:row["id"] ?? "")), !self.seen.contains(id),self.current != id,!self.queue.contains(where:{$0.0==id}) else { continue }
                    self.queue.append((id,row["sender_name"] as? String ?? "رسالة صوتية"))
                }
                if self.current==nil,let first=self.queue.first,let channel=self.channel { self.manager?.setActiveRemoteParticipant(PTParticipant(name:first.1,image:nil),channelUUID:channel) }
                self.next()
            }
        }
    }
    func urlSession(_ session: URLSession, task: URLSessionTask, willPerformHTTPRedirection response: HTTPURLResponse, newRequest request: URLRequest, completionHandler: @escaping (URLRequest?) -> Void) { completionHandler(nil) }
    private func send(path:String,method:String="GET",json:[String:Any]?=nil,auth:[String:String],completion:@escaping(Data?,Int)->Void) {
        guard let base=auth["base"], let token=auth["token"], let url=URL(string:base+path), url.scheme=="https" else { completion(nil,0);return }
        var request=URLRequest(url:url,timeoutInterval:20);request.httpMethod=method
        request.setValue("Bearer "+token,forHTTPHeaderField:"Authorization")
        if let json { request.httpBody=try? JSONSerialization.data(withJSONObject:json);request.setValue("application/json",forHTTPHeaderField:"Content-Type") }
        session.dataTask(with:request){data,response,_ in completion(data,(response as? HTTPURLResponse)?.statusCode ?? 0)}.resume()
    }
}
