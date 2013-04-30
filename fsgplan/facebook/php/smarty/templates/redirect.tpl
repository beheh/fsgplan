<!DOCTYPE HTML>
<html>
    <head>
        <title>{$title}</title>
        <link rel="stylesheet" type="text/css" href="css/canvas.css">
        <meta http-equiv="content-type" content="text/html; charset=utf-8"/>
        {if $location && $message == ""}<script type="text/javascript">{literal}
            window.onload = function() {
                window.top.location = "{/literal}{$location}{literal}";
            }
        {/literal}</script>{/if}
    </head>
    <body>
        <h1 style="display: inline;"><i style="display: inline; line-height: 20px; height: 16px; margin-right: 5px; vertical-align: middle;"><img src="icon.gif" height="16" width="16"></i>{$title}</h1>
        {if $message}<p>{$message}</p><p><a href="{$location}" target="_top">{if $description}{$description}{else}Weiter{/if}</a> &hellip;</p>{else}
        <p>Falls du nicht automatisch weitergeleitet wirst, klicke bitte <a href="{$location}" target="_top">hier</a> &hellip;</p>{/if}
    </body>
</html>
