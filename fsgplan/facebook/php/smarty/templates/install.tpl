<style type="text/css">
    .row_change {
        display: none;
    }
    
    #back {        
        margin-right: 4px;
    }
    
    #class_tip, #courses_tip {
        margin-left: 8px;
    }
    
    #back{if !$error}, #ufeedback{/if} {
        display: none;
    }
    
    #config_form {
    	margin-top: 16px;
    }
    
    #ufeedback {
    	margin-bottom: 8px;
    }
</style>
<form action="?c=install" method="post" id="config_form">
<p class="{if isset($error)}fberrorbox{/if}" id="ufeedback">{if isset($error)}{$error}{/if}</p><table>
<tr id="school_row"><td><label for="school">Schule</label></td><td><input type="hidden" id="school" name="school" value=""><span id="school">Friedrich-Schiller-Gymnasium Marbach</span></td></tr>
<tr id="class_row"><td><label for="class">Klasse</label></td><td><input type="text" id="class" name="class" value="{$class}" tabindex="1"><span id="class_tip">(<strong>wie im Vertretungsplan</strong>, z.B. 9a, 12 oder 13)</span><span id="class_change" class="row_change"><span id="class_result"></span> (<a href="#class" id="class_change_link" target="_top" tabindex="2">Ändern</a>)</span></td></tr>
<tr id="courses_row"><td><label for="courses">Kurse</label></td><td><input type="text" id="courses" name="courses" value="{$courses}" tabindex="3"><span id="courses_tip">(<strong>Vierstündige Kurse mit Großbuchstaben</strong>, z.B. D9,E5,spo4)</span><span id="courses_change" class="row_change"><span id="courses_result"></span> (<a href="#courses" id="courses_change_link" target="_top" tabindex="4">Ändern</a>)</span></td></tr>
<tr class="button"><td>&nbsp;</td><td><input type="button" value="&laquo; Zurück" id="back" class="uiButtonNormal" tabindex="6"><input type="submit" value="Speichern" id="submit" class="uiButtonSubmit" name="submit" tabindex="5"></td></tr>
</table></form>
<script type="text/javascript" src="js/install.js"></script>
