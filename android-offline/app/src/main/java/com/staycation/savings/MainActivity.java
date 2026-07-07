package com.staycation.savings;

import android.app.Activity;
import android.graphics.Color;
import android.os.Build;
import android.os.Bundle;
import android.view.Gravity;
import android.view.View;
import android.view.Window;
import android.view.WindowInsets;
import android.widget.FrameLayout;
import android.webkit.WebChromeClient;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;

public class MainActivity extends Activity {
    private WebView webView;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);

        webView = new WebView(this);
        FrameLayout root = new FrameLayout(this);
        root.setBackgroundColor(Color.rgb(12, 12, 24));
        root.addView(webView, new FrameLayout.LayoutParams(
            FrameLayout.LayoutParams.MATCH_PARENT,
            FrameLayout.LayoutParams.MATCH_PARENT,
            Gravity.CENTER
        ));
        applySystemBarInsets(root);
        setContentView(root);

        WebSettings settings = webView.getSettings();
        settings.setJavaScriptEnabled(true);
        settings.setDomStorageEnabled(true);
        settings.setDatabaseEnabled(true);
        settings.setAllowFileAccess(true);
        settings.setAllowContentAccess(false);
        settings.setMediaPlaybackRequiresUserGesture(false);

        webView.setWebViewClient(new WebViewClient());
        webView.setWebChromeClient(new WebChromeClient());
        webView.loadUrl("file:///android_asset/app/index.html");
    }

    private void applySystemBarInsets(FrameLayout root) {
        Window window = getWindow();
        window.setStatusBarColor(Color.rgb(12, 12, 24));
        window.setNavigationBarColor(Color.rgb(12, 12, 24));

        int fallbackTop = getSystemBarDimension("status_bar_height");
        int fallbackBottom = getSystemBarDimension("navigation_bar_height");
        root.setPadding(0, fallbackTop, 0, fallbackBottom);

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
            root.setOnApplyWindowInsetsListener(new View.OnApplyWindowInsetsListener() {
                @Override
                public WindowInsets onApplyWindowInsets(View v, WindowInsets insets) {
                    int top;
                    int bottom;
                    int left;
                    int right;

                    if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) {
                        android.graphics.Insets bars = insets.getInsets(WindowInsets.Type.systemBars());
                        top = bars.top;
                        bottom = bars.bottom;
                        left = bars.left;
                        right = bars.right;
                    } else {
                        top = insets.getSystemWindowInsetTop();
                        bottom = insets.getSystemWindowInsetBottom();
                        left = insets.getSystemWindowInsetLeft();
                        right = insets.getSystemWindowInsetRight();
                    }

                    v.setPadding(left, top, right, bottom);
                    return insets;
                }
            });
            root.requestApplyInsets();
        }
    }

    private int getSystemBarDimension(String name) {
        int id = getResources().getIdentifier(name, "dimen", "android");
        return id > 0 ? getResources().getDimensionPixelSize(id) : 0;
    }

    @Override
    public void onBackPressed() {
        if (webView != null && webView.canGoBack()) {
            webView.goBack();
        } else {
            super.onBackPressed();
        }
    }
}
