<style type="text/css">
    #class_change, #courses_change, #class_save_link, #courses_save_link {
        display: none;
    }
    
    #class_tip, #courses_tip {
        margin-left: 8px;
    }
    
    {if !$error}#ufeedback{/if} {
        display: none;
    }
    
    #config_form {
    	margin-top: 16px;
    }
    
    #ufeedback {
    	margin-bottom: 8px;
    }
</style>
<form action="?c=settings&o=changes" method="post" id="config_form"><table>
<p class="{if isset($error)}fberrorbox{/if}" id="ufeedback">{if isset($error)}{$error}{/if}</p>
<tr id="school_row"><td><label for="school">Schule</label></td><td><span id="school">Friedrich-Schiller-Gymnasium Marbach</span></td></tr>
<tr id="class_row"><td><label for="class">Klasse</label></td><td><input type="text" id="class" name="class" value="{$class}" tabindex="1"><span id="class_change" class="row_change"><span id="class_result">{$class}</span> (<a href="#" id="class_change_link" tabindex="2">Ändern</a><a href="#" id="class_save_link" tabindex="2">Überprüfen</a>)</span></td></tr>
<tr id="courses_row"><td><label for="courses">Kurse</label></td><td><input type="text" id="courses" name="courses" value="{$courses}" tabindex="3"><span id="courses_change" class="row_change"><span id="courses_result">{$courses}</span> (<a href="#" id="courses_change_link" tabindex="4">Ändern</a><a href="#" id="courses_save_link" tabindex="4">Überprüfen</a>)</span></td></tr>
<tr id="submit_row"><td></td><td><input type="submit" id="submit" value="Speichern" tabindex="5"></td></tr>
</table></form>
<script type="text/javascript" src="js/settings.js"></script>
{if !$courses}<script type="text/javascript">
$('#courses_row').hide();
</script>{/if}
