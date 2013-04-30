var step = 2;
var u_school, u_class, u_courses, working, b_need_courses;

function displayError(text) {
    showBox('fberrorbox', text);
    return true;
}   

function displayInfo(text) {
    showBox('fbinfobox', text);
    return true;
}

function displayStatus(text) {
	showBox('fbgreybox', text);
	return true;
}

function showBox(box_class, text) {
	if($('#ufeedback').text() != text || $('#ufeedback').attr('class') != box_class) {
    	$('#ufeedback').hide();
    }
    $('#ufeedback').attr('class', box_class);
    $('#ufeedback').text(text);
    $('#ufeedback').fadeIn();
    return true;
}

function hideBox() {
    $('#ufeedback').hide();
    return true;
}

function jumpToStep(step_new) {
    step = step_new;
    updateStep();
    return true;
}

function updateStep(init) {
    if(!init) hideBox();
    if(step >= 1) {
        $('#school_row').fadeIn();
    }
    else {        
        $('#school_row').fadeOut();
    }
    if(step >= 2) {
        $('#class_row').fadeIn();
        if(step >= 3) {
            $('#class').hide();
            $('#class_tip').hide();
            $('#class_change').fadeIn();
            $('#class').attr('disabled', true);
        }
        else {
            $('#class_change').hide();
            $('#class').fadeIn();
            $('#class_tip').fadeIn();
            $('#class').attr('disabled', false);
        }
        if(step == 2) {
            $('#class').focus();
        }
    }
    else {        
        $('#class_row').hide();
    }
    $('#back').attr('disabled', step < 3);
    if(step >= 3) {
        if(b_need_courses) {
            $('#courses').attr('disabled', step > 3);
            $('#courses_row').fadeIn();
            if(step >= 4) {
                $('#courses').hide();
                $('#courses_tip').hide();
                $('#courses_change').fadeIn();
                $('#courses').attr('disabled', true);
            }
            else {
                $('#courses_change').hide();
                $('#courses').fadeIn();
                $('#courses_tip').fadeIn();
                $('#courses').attr('disabled', false);
            }
            if(step == 3) {
                $('#courses').focus();
            }
        }
        else if(step == 3) {
            step++;
            return updateStep();
        }
    }
    else {
        $('#courses_row').hide();
    }
    $('#submit').attr('disabled', false);
    if(step >= 4) {
        if(step == 4) {
            $('#submit').focus();
        }
        $('#submit').val('Speichern');
    }
    else {
        $('#submit').val('Weiter »');
    }
    return true;
}

$('#class_change_link').click(function(e) {
    e.preventDefault();
    if(working) return false;
    jumpToStep(2);
});

$('#courses_change_link').click(function(e) {
    e.preventDefault();
    if(working) return false;
    jumpToStep(3);
});

$('#back').click(function() {
    if(working) return false;
    if(step == 4 && !b_need_courses) step--;
    jumpToStep(step-1);
});

function asyncResult(data) {
    working = false;
    if(!data.error) {
        if(data.courses != null) {
            b_need_courses = data.courses;
        }
		if(data.val) {
			switch(step) {
				case 2:
					u_class = data.val;
					$('#class').val(data.val);
					$('#class_result').text(data.val);
					break;
				case 3:
					u_courses = data.val;
					$('#courses').val(data.val);
					$('#courses_result').text(data.val);
					break;
			}
		}
        step++;
        updateStep();
        if(data.info) displayInfo(data.info);
    }
    else {
        updateStep();
        displayError(data.error);
    }
    return true;
}

$('#config_form').submit(function() {
    if(working) return false;
    if((step+1) >= 5) {
	    hideBox();
		$('#class').attr('disabled', false);
		$('#courses').attr('disabled', false);
		$('#submit').attr('disabled', true);
        $('#submit').val('Wird gespeichert …');
        $('#back').attr('disabled', true);
        working = true;
        return true;
    }
    else {
        var val;
        switch(step) {
            case 2:
                u_class = $('#class').val();
                $('#class_result').text(u_class);
                if(!u_class.length) {
                    displayInfo('Bitte gib deine Klasse ein.');
                    return false;
                }
                val = u_class;
                $('#class').attr('disabled', true);
                break;
           case 3:
                u_courses = $('#courses').val();
                $('#courses_result').text(u_courses);
                if(!u_courses.length) {
                    displayInfo('Bitte gib deine Kurse ein.');
                    return false;
                }
                val = u_courses;
                $('#courses').attr('disabled', true);
                break;
           default:
                return false;
                break;
        }
        $.ajax({url:'?c=ajax&a=install', data:{step:step,val:val}, success:function(a) {asyncResult(a);}, error:function(a){asyncResult({error:'Ein Fehler ist aufgetreten. Bitte versuch es später erneut.'});}});
        $('#submit').attr('disabled', true);
        $('#back').attr('disabled', true);
        $('#submit').val('Wird überprüft …');
	    working = true;
        return false;
    }
});

document.ready = function() {
    $('#courses_row').hide();
    $('#courses_tip').hide();        
    $('#back').show();
    step = 2;
    updateStep(true);
};
