<!DOCTYPE HTML>
<html>
    <head>
        <title>{$title}</title>
        <link rel="stylesheet" type="text/css" href="css/reset.css">
        <link rel="stylesheet" type="text/css" href="css/canvas.css">
        <script type="text/javascript" src="js/jquery-1.7.2.min.js"></script>
        <script type="text/javascript">
            function getParameterByName(name)
            {
              name = name.replace(/[\[]/, "\\\[").replace(/[\]]/, "\\\]");
              var regexS = "[\\?&]" + name + "=([^&#]*)";
              var regex = new RegExp(regexS);
              var results = regex.exec(window.location.search);
              if(results == null)
                return "";
              else
                return decodeURIComponent(results[1].replace(/\+/g, " "));
            }
        </script>
        <meta http-equiv="content-type" content="text/html; charset=utf-8"/>
    </head>
    <body>
		<div id="fb-root"></div>
        {literal}<script>(function(d){
          var js, id = 'facebook-jssdk'; if (d.getElementById(id)) {return;}
          js = d.createElement('script'); js.id = id; js.async = true;
          js.src = "//connect.facebook.net/de_DE/all.js#xfbml=1";
          d.getElementsByTagName('head')[0].appendChild(js);
        }(document));
        window.fbAsyncInit = function() {
          FB.init();
          FB.Canvas.setAutoGrow();
        }
        </script>{/literal}
        <h1 style="display: inline-block; margin-bottom: 6px;"><a href="{$root}" target="_top"><i style="display: inline-block; line-height: 20px; height: 16px; margin-right: 5px;"><img src="icon.gif" height="16" width="16" style="vertical-align: text-top;"></i>{$title}</a></h1>
        <div id="content">{$content}</div>
		{if isset($footer_left)}<p class="footer" style="float: left;">{$footer_left}</p>{/if}
        <p class="footer">{$footer}</p>
        <!-- Piwik --> 
        <script type="text/javascript">
        var pkBaseURL = (("https:" == document.location.protocol) ? "https://example.com/piwik/" : "http://example.com/piwik/");
        document.write(unescape("%3Cscript src='" + pkBaseURL + "piwik.js' type='text/javascript'%3E%3C/script%3E"));
        </script><script type="text/javascript">
        try {
        var piwikTracker = Piwik.getTracker(pkBaseURL + "piwik.php", 1);
        piwikTracker.setDocumentTitle("Facebook");
        piwikTracker.setCustomVariable(1, 'Quelle', getParameterByName('fb_source'), 'visit');
        piwikTracker.setCustomVariable(2, 'Referenz', getParameterByName('ref'), 'visit');
        piwikTracker.setCustomVariable(3, 'Requests', getParameterByName('count'), 'visit');
        piwikTracker.setCustomVariable(4, 'Positionierung', getParameterByName('fb_bmpos'), 'visit');
        piwikTracker.trackPageView();
        piwikTracker.enableLinkTracking();
        } catch( err ) {}
        </script><noscript><p><img src="https://example.com/piwik/piwik.php?idsite=1" style="border:0" alt=""></p></noscript>
        <!-- End Piwik Tracking Code -->
    </body>
</html>
