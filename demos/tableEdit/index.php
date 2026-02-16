<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <!-- fusion-lib baseline -->
        <link rel="stylesheet" href="/fusion-lib/css/fusion-plain.css">

        <title>Login</title>
    </head>

    <table id='t' class='fusion-table fusion-table-fit'>
        <thead class='fusion-table-head'>
            <tr>
                <th colspan=2>Sticky columns</th>
                <th colspan=4>normal columns</th>
            <tr>
                <th>Column 0<br>Number</th>
                <th>Column 1<br>Text</th>
                <th>column 2<br>Date</th>
                <th>Other column<br>Time</th>
                <th>Column 4<br>Email</th>
                <th data-rotate >Column 5<br>Active</th>
            </tr>
        </thead>
        <tbody class='fusion-table-body'>
            <tr><td></td><td>2asasa</td><td></td><td></td><td>otto@tonne.to</td><td>no</td></tr>
            <tr><td></td><td>2asasa</td><td></td><td>12:55</td><td></td><td>no</td></tr>
            <tr><td>4</td><td>2asasa</td><td>10.06.1954</td><td>0030</td><td></td><td>no</td></tr>
            <tr><td></td><td>2asasa</td><td></td><td>1045</td><td></td><td>no</td></tr>
            <tr><td>-7.9</td><td>2asa</td><td>03/30/1955</td><td>abc</td><td>hugo@exsitiert.net</td><td>no</td></tr>
        </tbody>
    </table>                    

    <p>
        To save edits and jump into other cells use:
    <ul>
        <li>Return
        <li>TAB
        <li>SHIFT-TAB
        <li>UP-ARROW
        <li>DOWN-ARROW 
        <li>Click in any other cell
    </Ul>
    Any of the above events will save content here on the page, and move the input field accordingly.
    <p>Use ESCAPE to exit edit mode.
        Inside the table, the context menu  offers some more functionality <p>
        There is no backend attached to make changes of table content persistend. <br>
        When you refresh the page all changes are gone

    <p>
        When you scroll the page content towards and over the left edge, you will see that the table has two sticky
        columns from the left.<br> The same goes for the entire table header, scroll the page content towards
        and over the top.<br>
        The geometry/layout of the sticky parts, will also be maintained if you sort the table<br> or  insert, copy or delete rows
        or if you change the content of table cells.
    <p>


    <div style="width:4000px;height:4000px"> </div><!-- will force scollbars neede her for demo   -->
    <script src="../../js/stickyCSS.js"></script>           
    <script src="../../js/sortTableNew.js"></script>
    <script src="../../js/contextMenu.js"></script>
    <script src="../../js/justDialogs.js"></script>
    <script src="../../js/cellResize.js"></script>
    <script src="../../js/vertical.js"></script>   
    <script src="../../js/myBackend.js"></script>
    <script src="../../js/calendarium.js"></script>
    <script src="../../js/validator.js"></script>
    <script src="../../js/addConfirm.js"></script>

    <script src="../../js/basicTableEdit.js"></script>
    <script src="startGridEdit.js"></script>

    <script>
        window.addEventListener('load', () => {
            rotateHeadCell('t');
            startGridEdit('t');
        }, false);
    </script>

</body>
</html>
