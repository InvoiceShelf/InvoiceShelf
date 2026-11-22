import UIKit
import Capacitor

// The app's one window scene. UIKit requires the scene lifecycle from the iOS 27
// SDK on: an app built against it that still keeps its window on the app
// delegate traps at launch, before the first frame. The scene manifest in
// Info.plist names this class and the Main storyboard, so UIKit builds the
// window and the bridge view controller here; nothing below needs to.
//
// Capacitor 7 only knows the app delegate entry points for opened URLs and
// universal links, and under scenes UIKit stops calling those. They arrive here
// instead, and are handed to ApplicationDelegateProxy the way AppDelegate used
// to, so the App plugin's appUrlOpen and getLaunchUrl keep working.
class SceneDelegate: UIResponder, UIWindowSceneDelegate {

    var window: UIWindow?

    func scene(_ scene: UIScene, willConnectTo session: UISceneSession, options connectionOptions: UIScene.ConnectionOptions) {
        // A cold launch from a URL or a universal link delivers it here, not
        // through the callbacks below. Forwarding it records it as the proxy's
        // lastURL, which is what getLaunchUrl reads once the bridge is up.
        openURLs(connectionOptions.urlContexts)

        if let userActivity = connectionOptions.userActivities.first {
            continueActivity(userActivity)
        }
    }

    func scene(_ scene: UIScene, openURLContexts URLContexts: Set<UIOpenURLContext>) {
        openURLs(URLContexts)
    }

    func scene(_ scene: UIScene, continue userActivity: NSUserActivity) {
        continueActivity(userActivity)
    }

    private func openURLs(_ contexts: Set<UIOpenURLContext>) {
        for context in contexts {
            var options: [UIApplication.OpenURLOptionsKey: Any] = [
                .openInPlace: context.options.openInPlace
            ]
            if let sourceApplication = context.options.sourceApplication {
                options[.sourceApplication] = sourceApplication
            }
            if let annotation = context.options.annotation {
                options[.annotation] = annotation
            }

            _ = ApplicationDelegateProxy.shared.application(UIApplication.shared, open: context.url, options: options)
        }
    }

    private func continueActivity(_ userActivity: NSUserActivity) {
        _ = ApplicationDelegateProxy.shared.application(UIApplication.shared, continue: userActivity) { _ in }
    }

}
