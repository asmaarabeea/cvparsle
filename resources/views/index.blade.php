<!DOCTYPE html>
<html>
<head>
	<title></title>
	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">

	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
	<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js" type="text/javascript"></script>

</head>
<body>
<table width="600">
	<br>
	<form action="{{ url('/parse') }}" method="post" id="my_form" enctype="multipart/form-data">

	<tr>
	<td width="20%">Select file</td>
	<td width="80%">
		<input type="file" name="cvs[]" id="cvs" multiple class="btn btn-info form-control"/>
	</td>
	</tr>

	<tr>
	<td>Submit</td>
	<td>
		<input type="submit" name="submit" value="Submit" class="btn btn-primary form-control" />
	</td>
	</tr>

	</form>
</table><br>
 <div class="progress">
        <div class="progress-bar" role="progressbar" aria-valuenow="70"
             aria-valuemin="0" aria-valuemax="100" style="width:0">
            <span id="fullResponse"></span>
        </div>
    </div>
    <h4 class="progressTest"></h4>
<!-- <div id="results"></div> -->
</body>
</html>

<script type="text/javascript">

	$("#my_form").submit(function(event){

		var lastResponseLength = false;

	    event.preventDefault(); //prevent default action 
	    var post_url = $(this).attr("action"); //get form action url
	    var request_method = $(this).attr("method"); //get form GET/POST method
	    var form_data = new FormData(this); //Creates new FormData object
	    $.ajax({
	        url : '/parse',
	        type: request_method,
	        data : form_data,
	        contentType: false,
	        cache: false,
	        dataType: "json",
	        processData:false,

            xhrFields: {
            // Getting on progress streaming response
            onprogress: function(e)
            {
                var progressResponse;
                var response = e.currentTarget.response;
                if(lastResponseLength === false)
                {
                    progressResponse = response;
                    lastResponseLength = response.length;
                    console.log(response.length);

                }
                else
                {
                    progressResponse = response.substring(lastResponseLength);
                    lastResponseLength = response.length;
                    console.log(response.length);

                }

                var parsedResponse = JSON.parse(progressResponse);
                $('.progressTest').append(progressResponse + '<br>');
                $('#fullResponse').append(parsedResponse.message + ' , ');
                $('.progress-bar').css('width', parsedResponse.progress + '%');
            },

            success: function(response) {
                // $("#results").append(response);
                console.log('Complete response = ' + response);
            },
            error: function(error) {
                console.log(error);
            }
        }

	    // }).done(function(response){ //
	    //     $("#results").html(response);

	    });


	});
</script>
