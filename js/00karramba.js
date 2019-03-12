$(function()  { 
	var page=1;

	$('msg').delay(800).fadeOut(2000);
	$("body").on("click", "#finished_selecting_groups",function() {
		if($(this).css("opacity")==1) {
			$(this).closest("form").submit();
			$("#groups_list").slideUp(200);
		}
	});

	$("body").on("click", "#groups_list",function() {//{{{
		var collect_groups_ids=[];
		var collect_quizes_ids=[];
		$(".div-yes.groups").each(function() {
			collect_groups_ids.push($(this).children('input').val());
		});
		$(".div-yes.quizes").each(function() {
			collect_quizes_ids.push($(this).children('input').val());
		});
		$(".groups_collector_ids").val(collect_groups_ids.join());
		$(".quizes_collector_ids").val(collect_quizes_ids.join());
		if(collect_quizes_ids.length>0 && collect_groups_ids.length>0) { 
			$('#finished_selecting_groups').css({ "background": "#06a", "opacity": "1"});
		} else {
			$('#finished_selecting_groups').css({ "background": "none", "opacity": "0.3"});
		}
	});

//}}}
	$("body").on("click", "#finished_selecting_owners",function() {//{{{
		var collect_owners_ids=[];
		$(".div-yes.owners").each(function() {
			collect_owners_ids.push($(this).children('input').val());
		});
		$("#owners_collector_ids").val(collect_owners_ids.join());
		$("#owners_list").slideUp(200);
	});
//}}}
	$("body").on("click", ".div-yes",function() {//{{{
	    $(this).removeClass("div-yes");
	    $(this).addClass("div-no");
	});
//}}}
	$("body").on("click", ".div-no",function() {//{{{
	    $(this).removeClass("div-no");
	    $(this).addClass("div-yes");
	});
//}}}
	$("body").on("click", "fatal", function() {//{{{
		$(this).slideUp(200);
	});

//}}}
	$("body").on("click", "cannot", function() {//{{{
		$(this).slideUp(200);
	});

//}}}
	$("body").on("click", "dd", function() {//{{{
		$(this).slideUp(200);
	});
//}}}
	$("body").on("click", "debug_request", function() {//{{{
		$(this).slideUp(200);
	});
//}}}
	$("body").on("click", "debug_session", function() {//{{{
		$(this).slideUp(200);
	});
//}}}
	$("body").on("click", "debug_server", function() {//{{{
		$(this).slideUp(200);
	});
//}}}
	$("body").on("click", ".close", function() {//{{{
		$(this).parent().slideUp(200);
	});
//}}}
	$("body").on("click", "#choose_groups_button", function() {//{{{
		$("sliding_div").slideToggle(200);
	});
//}}}
	$("body").on("click", "#choose_owners_button", function() {//{{{
		$("sliding_div").slideToggle(200);
	});
//}}}
	$("body").on("click", "#need_debug_request", function() {//{{{
		$("debug_request").slideToggle(200);
	});
//}}}
	$("body").on("click", "#need_debug_session", function() {//{{{
		$("debug_session").slideToggle(200);
	});
//}}}
	$("body").on("click", "#need_debug_server", function() {//{{{
		$("debug_server").slideToggle(200);
	});
//}}}
	$("body").on("click", "#need_images", function() {//{{{
		$("dropzone_form").slideToggle(500);
		$("q_howto").slideUp(500);
	});
//}}}
	$("body").on("dblclick", "dropzone_form", function() {//{{{
		$("dropzone_form").slideUp(500);
		$("q_howto").slideUp(500);
	});
//}}}
	$("body").on("dblclick", "q_howto", function() {//{{{
		$("dropzone_form").slideUp(500);
		$("q_howto").slideUp(500);
	});
//}}}
	$("body").on("click", "#q_instructions", function() {//{{{
		$('q_howto').slideToggle(500);
		$("dropzone_form").slideUp(500);
	});
//}}}
	$("body").on("click", ".answer_yes", function() {//{{{
		$(this).attr("class", "answer_no");
		$(this).children('input').val('0');
	});
//}}}
	$("body").on("click", ".answer_no", function() {//{{{
		$(this).attr("class", "answer_yes");
		$(this).children('input').val('1');
	});
//}}}
	$("body").on("click", "next", function() {//{{{
		page+=1;
		if (page>parseInt($('qtotal').text())) { page=1; }
		$('qnumber').text(page);
		$("page").attr("class", "invisible");
		$("#p"+page).attr("class", "visible");
		if (page==parseInt($('qtotal').text())) { $("#karrambaSubmit").attr("class", "visible"); }
	});
//}}}
	$("body").on("click", "prev", function() {//{{{
		page-=1;
		if (page<1) page=parseInt($('qtotal').text()); 
		$('qnumber').text(page);
		$("page").attr("class", "invisible");
		$("#p"+page).attr("class", "visible");
		if (page==parseInt($('qtotal').text())) { $("#karrambaSubmit").attr("class", "visible"); }
	});
//}}}
	if ($("body").find("#table_monitor_solving").length > 0)  {//{{{
		var x = setInterval(function() {
			$.ajax({
				url: "ajax.php",
				dataType: "json",
				data: { studentsSolvingQuizMonitor: 1 },
			})
			.done(function(data) {
				$("total_logins").text(data[1]);
				$('#table_monitor_solving').html("<thead><th>Quiz<th>Group<th>Student<th>Start<th>Points</thead>");
				$('#table_monitor_solving').append(data[0]);
			});

		}, 2000);
	}

	if ($("body").find("display_clock").length > 0)  {
		var x = setInterval(function() {
			var time = new Date();
			$("display_clock").text(("0" + time.getHours()).slice(-2)   + ":" + ("0" + time.getMinutes()).slice(-2) + ":" + ("0" + time.getSeconds()).slice(-2));
		}, 1000);
	}
//}}}
	$('#inputStudentLogin').autocomplete({//{{{
	// example: user=Jon Show, id=611
	// label appears in the ajax begining, value is after label is chosen. At the end user will have 'Jon Snow' changing to '611', which we don't want, therefore:
	// <input label='Jon Snow' value='Jon Snow'> 
	// <input type=hidden name=user value=611>
			source: function( request, response ) {
				$.ajax({
					url : 'ajax.php',
					dataType: "json",
					data: { studentLoginChars: $("#inputStudentLogin").val() },
					success: function( data ) {
					response($.map( data, function( item ) {
						return {
							label: item[0],
							value: item[0],
							data: item[1]
						}
					}));
				}
			});
		},
		autoFocus: true,
		minLength: 2,      	
		select: function( event, ui ) {
			$("#inputHiddenStudentId").val(ui.item.data);
		}
	});
//}}}
	$(function() { //{{{ Tooltips - dymki/pomoc
		var tooltips = $( "[title]" ).tooltip({
			open: function(event, ui){
				ui.tooltip.css('max-width', '400px');
			},
			content: function () {
			  return $(this).prop('title');
			}
		});
	});//}}}


});

function timerStudentClock(){//{{{
	// Internally timestamp is in milisconds. timeout comes as seconds from php.
	// Deadline is a now()+timeout timestamp in the future.
	// We just see current seconds, do the difference, then format minutes and seconds.
	// The deadline initialization/login/logout/continuations are taken care of in index.php
	// We don't mind if student alters their clock - we have independent clock
	// on the server which is used on quiz submission.

	var timeout=parseInt($("timeout").text());  // seconds
	var deadline=new Date(Date.now() + timeout*1000); 
	var minutes;
	var seconds;

	setInterval(function() {
		var delta = (deadline - new Date())/1000; // 600, 599, 598, ...
		console.log("delta", delta);
		minutes = Math.floor(delta / 60);
		seconds = Math.floor(delta % 60);
		if(minutes >= 0) { 
			$("clock").text(pad(minutes) + ":" + pad(seconds));
		} else {
			$("clock").text("END");
			$("#karramba").submit();
		}
	}, 1000); // update about every second
}
//}}}
function startQuiz() {	//{{{
	$('answer').children('input').val('0');
	$("page").attr("class", "invisible");
	$("#p1").attr("class", "visible");
	timerStudentClock();
}
//}}}
function pad(num) {//{{{
    var s = "00" + num;
    return s.substr(s.length-2);
}
//}}}
