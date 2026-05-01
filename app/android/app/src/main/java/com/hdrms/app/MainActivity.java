package com.hdrms.app;

import android.os.Bundle;
import android.webkit.WebSettings;
import com.getcapacitor.BridgeActivity;

public class MainActivity extends BridgeActivity {
    @Override
    public void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);

        WebSettings settings = getBridge().getWebView().getSettings();
        String ua = settings.getUserAgentString();
        if (ua != null && ua.contains(" wv")) {
            settings.setUserAgentString(ua.replace(" wv", ""));
        }

        // Inject the UI fixes directly into the remote website as it loads
        getBridge().getWebView().setWebViewClient(new com.getcapacitor.BridgeWebViewClient(getBridge()) {
            @Override
            public void onPageFinished(android.webkit.WebView view, String url) {
                super.onPageFinished(view, url);
                String css = "/* Global Reset */ " +
                             "html, body { overflow-x: hidden !important; width: 100% !important; margin: 0 !important; padding: 0 !important; }" +
                             ".app-shell { min-height: 100vh !important; position: relative !important; display: flex !important; flex-direction: column !important; }" +
                             
                             "/* Topbar Fix */ " +
                             ".topbar { position: fixed !important; top: 0 !important; left: 0 !important; right: 0 !important; z-index: 1000 !important; padding-top: 36px !important; background: white !important; border-bottom: 1px solid rgba(0,0,0,0.08) !important; height: auto !important; min-height: 90px !important; }" +
                             ".topbar .container { width: 100% !important; height: 54px !important; padding: 0 20px !important; display: flex !important; align-items: center !important; justify-content: space-between !important; }" +
                             
                             "/* Layout Handling */ " +
                             ".status-banner { margin-top: 90px !important; }" +
                             ".main-pad { padding-top: 20px !important; padding-bottom: 160px !important; flex: 1 !important; }" +
                             ".container { width: 100% !important; max-width: 100% !important; padding-left: 20px !important; padding-right: 20px !important; box-sizing: border-box !important; }" +
                             ".row { width: 100% !important; margin-left: 0 !important; margin-right: 0 !important; min-height: auto !important; align-items: center !important; display: flex !important; flex-direction: column !important; }" +
                             ".col-12, .col-md-8, .col-lg-5 { width: 100% !important; max-width: 500px !important; padding: 0 !important; margin: 0 auto !important; }" +
                             
                             "/* UI Elements */ " +
                             ".surface { margin-bottom: 24px !important; border-radius: 20px !important; }" +
                             ".surface-body { padding: 24px !important; }" +
                             ".mobile-tabbar { position: fixed !important; bottom: 12px !important; left: 12px !important; right: 12px !important; height: 64px !important; z-index: 3000 !important; border-radius: 16px !important; box-shadow: 0 8px 32px rgba(0,0,0,0.25) !important; }" +
                             
                             "/* Stat Grid Responsive Fix */ " +
                             ".stat-grid { grid-template-columns: 1fr !important; gap: 12px !important; }" +
                             ".stat-value { font-size: 1.2rem !important; word-break: break-word !important; line-height: 1.2 !important; }" +
                             ".stat-tile { padding: 16px !important; min-height: auto !important; height: auto !important; }" +
                             ".hero-grid { grid-template-columns: 1fr !important; gap: 20px !important; }" +

                             "/* Fix for hidden elements */ " +
                             ".risk-high { margin-bottom: 40px !important; position: relative !important; z-index: 10 !important; }";

                view.evaluateJavascript("javascript:(function() {" +
                        "function applyPatch() {" +
                        "  var id = 'hg-native-responsive-patch';" +
                        "  var existing = document.getElementById(id);" +
                        "  if (existing) { existing.innerHTML = '" + css + "'; return; }" +
                        "  var style = document.createElement('style');" +
                        "  style.id = id;" +
                        "  style.innerHTML = '" + css + "';" +
                        "  document.head.appendChild(style);" +
                        "}" +
                        "applyPatch();" +
                        "setInterval(applyPatch, 1000);" + // Force re-apply every second
                        "})()", null);
            }
        });
    }
}
