var step;
var u_class, u_courses, u_class_start, u_courses_start, working, working, b_need_courses, b_courses_suppress_animation;

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

$('#class_change_link').click(function(e) {
    if(e) e.preventDefault();
    if(working) return false;
	resetForms();
	if(!u_courses && b_need_courses) {
		$('#courses_row').hide();
	}
	$('#submit').attr('disabled', true);
	$('#submit_row').fadeIn();	
	$('#class_change_link').hide();
	$('#class_save_link').fadeIn();
	$('#class_result').hide();
	$('#class').fadeIn();
	$('#class').focus();
});

$('#class_save_link').click(function(e) {
    if(e) e.preventDefault();
    if(working) return false;
	step = 2;
	$('#config_form').submit();
});

$('#courses_change_link').click(function(e) {
    if(e) e.preventDefault();
    if(working) return false;
	resetForms();
	$('#submit').attr('disabled', true);
	$('#submit_row').fadeIn();
	$('#courses_change_link').hide();
	$('#courses_save_link').fadeIn();	
	$('#courses_result').hide();
	$('#courses').fadeIn();
	$('#courses').focus();
});

$('#courses_save_link').click(function(e) {
    if(e) e.preventDefault();
    if(working) return false;
	step = 3;
	$('#config_form').submit();
});

function resetForms() {
	hideBox();
	$('#class_save_link').hide();
	$('#class_change_link').fadeIn();
	$('#class').hide();
	$('#class').val(u_class);
	$('#class_result').fadeIn();
	$('#class_result').text(u_class);
	if(!b_courses_suppress_animation) {
		$('#courses_save_link').hide();
		$('#courses_change_link').fadeIn();
		$('#courses').hide();
		$('#courses').val(u_courses);
		$('#courses_result').fadeIn();
		$('#courses_result').text(u_courses);
	}
	else {
		b_courses_suppress_animation = false;
	}
	if(b_need_courses) {
		$('#courses_row').fadeIn();
	}
	else {
		$('#courses_row').hide();
	}
	step = 4;
}

function asyncResult(data) {
    working = false;
	$('#class').attr('disabled', false);
	$('#courses').attr('disabled', false);
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
		if(data.info) {
			resetForms();
			displayInfo(data.info);
			$('#submit').attr('disabled', false);
			$('#submit').focus();
		}
		else if(b_need_courses && !u_courses) {
			b_courses_suppress_animation = true;
			resetForms();
			$('#courses_change_link').click();
			displayInfo('Eingabe von Kursen erforderlich.');
        }
		else {
			resetForms();
			if(u_class_start == u_class && (!b_need_courses || u_courses_start == u_courses)) {
				$('#submit_row').hide();
				displayStatus('Das sind bereits deine aktuellen Daten.');
			}
			$('#submit').attr('disabled', false);
			$('#submit').focus();
		}
    }
    else {
		displayError(data.error);
    }
    return true;
}

$('#config_form').submit(function() {
    if(working) return false;
	hideBox();
	switch(step) {
		case 2:
			$('#class_result').text($('#class').val());
			 if(!$('#class').val().length) {
				displayInfo('Bitte gib deine Klasse ein.');
				return false;
			}
			val = $('#class').val();
			$('#class').attr('disabled', true);
			break;
		case 3:
			$('#courses_result').text($('#class').val());
			if(!$('#class').val().length) {
				displayInfo('Bitte gib deine Kurse ein.');
				return false;
			}
			val = $('#courses').val();
			$('#courses').attr('disabled', true);
			break;
		case 4:
			working = true;
			$('#class').attr('disabled', false);
			$('#courses').attr('disabled', false);
			$('#submit').attr('disabled', true);
			$('#submit').val('Wird gespeichert …');
			return true;
			break;
		default:
			return true;
			break;
	}
	$.ajax({url:'?c=ajax&a=settings', data:{step:step,val:val}, success:function(a) {asyncResult(a);}, error:function(a){asyncResult({error:'Ein Fehler ist aufgetreten. Bitte versuch es später erneut.'});}});
	working = true;
	return false;
});

document.ready = function() {
	u_class_start = u_class = $('#class').val();
	u_courses_start = u_courses = $('#courses').val();
	b_need_courses = u_courses != '';
	$('#class').hide();
	$('#class').keypress(function(e) {
		if(e.keyCode != 13) return true;
		$('#class_save_link').click();
	});
	$('#courses').hide();
	$('#courses').keypress(function(e) {
		if(e.keyCode != 13) return true;
		$('#courses_save_link').click();
	});
	$('#submit_row').hide();
	$('#class_change').show();
	$('#courses_change').show();
};
