<!DOCTYPE html lang="de">
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>makeTabel Demo</title>  
        <link rel="stylesheet" href="/fusion-lib/css/fusion-plain.css">

    </head>
    <body>
        <div id='tableSpace'>
        </div>

        <div style="width:4000px;height:4000px"> </div><!-- will force scollbars neede her for demo   -->                   

        <script src='myBackend.js'></script>
        <script src='filterTable.js'></script>
        <script src='sortTableNew.js'></script>
        <script src='stickyCSS.js'></script>
        <script src='tooltip.js'></script>
        <script src='justDialogs.js'></script>


        <script>
            // boot page
            let backend = myBackend();
            let dialog = justDialogs('de', 'bulma');

            function startPage() {

                backend.fetchHTML('tableSpace', 'getTable.php', {}, (recPkg) => {
                    // when page is loaded

                    makeSticky('tt*', {col: 0, loff: 0, toff: 0});
                    sortTable('tt*');
                    toolTip();
                    dialog.myInform('Here we are', true)


                });
            }

            startPage();

        </script>



    </body>
</html>
