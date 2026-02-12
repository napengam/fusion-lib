<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Dialogs Demo</title>  
         <link rel="stylesheet" href="/fusion-lib/css/fusion-plain.css">
    </head>
    <body>
        <div class="container">
            <div class="content">
                <section class="section">
                    <p id="out"><b>&nbsp;</b></p> 
                    <div style="margin-left:20px">                    
                        <button  class="button has-background-light" onclick='theDialogs.myInform("Informatione Grande non modal", true)'>Just show some information</button>
                        <button  class="button has-background-light"  onclick='theDialogs.myLogin("Please Log In", save, no)'>Login Dialog</button>
                        <button  class="button has-background-light"  onclick='theDialogs.myAlert("The Alert box\nYou made it csaaaaaaaaaaaaaaaaaaaaa aaa!")'>Show Alert Box</button>
                        <button  class="button has-background-light"  onclick='theDialogs.myConfirm("<h2>Please confirm</h2>", callYes, callNo)'>Show Confirmation Dialog</button>       
                        <button  class="button has-background-light"  onclick='theDialogs.myPrompt("<h2>Please enter</h2>", "666", callOnEnter)'>Show Prompt Dialog</button>       
                        <button  class="button has-background-light" onclick='theDialogs.myUpload("Upload a file", "dummyUpload.php", {path: "f:/upload/"})'>Upload</button>
                    </div>
                </section>
            </div>
        </div>


        <script src="../../js/justDialogs.js"></script>   
        <script>

                            theDialogs = justDialogs();

                            theDialogs = justDialogs();

                            function callYes() {
                                document.getElementById('out').innerHTML = '<b style="color:green">The confirm box YES button was pressed';
                            }
                            function callNo() {
                                document.getElementById('out').innerHTML = '<b style="color:red">The confirm box NO button was pressed';
                            }
                            function callOnEnter(v) {
                                document.getElementById('out').innerHTML = '<b style="color:black">' + htmlentity(v);
                            }
                            function callOnSelect(option) {
                                document.getElementById('out').innerHTML = '<b style="color:black">' + htmlentity(option.value);
                            }
                            function save(n, p) {
                                document.getElementById('out').innerHTML = '<b style="color:black">' + htmlentity(n) + '/' + htmlentity(p);
                            }
                            function no() {
                                document.getElementById('out').innerHTML = '<b style="color:black">';
                            }

                            function htmlentity(value) {
                                value = value.replace(/&/gi, "&amp;");
                                value = value.replace(/</gi, "&lt;");
                                value = value.replace(/>/gi, "&gt;");
                                value = value.replace(/"/gi, "&quot;");
                                value = value.replace(/'/gi, "&#039;");
                                return value;
                            }
        </script>  
    </body>
</html>
