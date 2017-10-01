$(document).ready(function(){
	$(".table tr").each(function() {
		var given = $(this).find(".given").html();
		var actual = $(this).find(".actual").html();
		if (given == actual) {
			$(this).find(".given").css("background-color", "green");
			$(this).find(".actual").css("background-color", "green");
		} else {
			$(this).find(".given").css("background-color", "red");
			$(this).find(".actual").css("background-color", "green");
		}
	});
});