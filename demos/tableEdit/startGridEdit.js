function startGridEdit(t) {

    var tEdit, dialog = justDialogs(), backend = myBackend(),
            dictionary;

    // *****************************************
    // create table editor for given table
    // inject the function for change of cell data
    // inject access to error dialog
    // ******************************************


    tEdit = tableEdit(t);
    tEdit.setChangeCallBack(changeValue);
    tEdit.setErrorCallBack(teError);
    tEdit.setConfirmCallBack(teConfirm);

    // *****************************************
    // function called from tabelEdit in case 
    // cell data has changed. We pass it to the
    // back end 
    // ******************************************

    function changeValue(params, respondFunc) {
        backend.callDirect('dummy.php', params, respondFunc);
    }
    // *****************************************
    // function to access what ever dialog
    // system is used.
    // ******************************************

    function teError(message, func = null) {
        dialog.myAlert(message, func);
    }

    function teConfirm(message, yes, no) {
        dialog.myConfirm(message, yes, no);
    }
    // *****************************************
    // Here we create a dictionary taylord for
    // the validtor we pass into the table editor 
    // ****************************************** 
    dictionary = [
        {name: 'col0', len: 10, type: 'number'},
        {name: 'col1', len: 20, type: 'text', confirm: 'yes'},
        {name: 'col2', len: 10, type: 'date', skip: 'yes'},
        {name: 'col3', len: 5, type: 'time'},
        {name: 'col4', len: 60, type: 'email'},
        {name: 'col5', len: 10, type: "select", options: ["Yes", "No", "Maybe"], confirm: 'no'}
    ];
    // *****************************************
    // merge with default:
    // ******************************************
    let dictDefault = {name: '', type: 'text', edit: 'yes', must: 'no', skip: 'no', length: '', confirm: ''};
    dictionary = dictionary.map((elem) => {
        return Object.assign({}, dictDefault, elem);
    });

    // *****************************************
    // inject dictionary along with the validator
    // into tabelEditor so it knows
    // about the type of the column/cell data
    // ******************************************

    tEdit.setValidator(validator, dictionary);
    tEdit.setDictionary(dictionary);
    // *****************************************
    // give the table a sticky header. 
    // ******************************************

    let theTable = tEdit.theTable();
    makeSticky(theTable, {col: 2, loff: '', toff: 'pghead'});
    // *****************************************
    // make cells resizable
    // ******************************************
    initResize(t);
    window.calendar = hgsCalendar();
    window.calendar.backEnd('fetchCalendar.php');
    addConfirm({confirmFn: dialog.myConfirm});
    HGS_CALENDAR.backEnd('fetchCalendar.php');
}