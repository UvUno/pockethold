    // Run on page load.
    initFunction();

    /*

    */
    //Run on first visit/page refresh

    /**
     * initFunction - Run on pageload
     */
    function initFunction() {

      wretch(fetchurl + '?ajax=status')
        .get()
        .notFound(error => { /* ... */ })
        .unauthorized(error => { /* ... */ })
        .error(418, error => { /* ... */ })
        .json(json => {

          if (response.status = 'prepare') {
            //  block of code to be executed if condition1 is true
          } else if (response.status = 'waiting') {
            //  block of code to be executed if the condition1 is false and condition2 is true.
            $("#progressdiv").html(waiting);
            getProgress(url);
            setTimeout(logScroll, 1000 );
            setTimeout(initFunction, 10000 );

          } else if (response.status = 'cleanup') {
            $("#progressdiv").html(cleanup);
            //  output cleanup button
          } else if (response.status = 'reqnotmet'){
            // Out put what fails
            // Output Download logs for issue creation
            $("#progressdiv").html(notpossible);
            //Not going to work. Need to work on the JSON parsing...
            let table = document.querySelector("table");
            let data = Object.keys(resonse[0]);
            generateTable(table, mountains); // generate the table first
            generateTableHead(table, data); // then the head

          } else {


          }

        })
    }

    /*
    Source gotten from: https://www.valentinog.com/blog/html-table/
    */
    function generateTableHead(table, data) {
      let thead = table.createTHead();
      let row = thead.insertRow();
      for (let key of data) {
        let th = document.createElement("th");
        let text = document.createTextNode(key);
        th.appendChild(text);
        row.appendChild(th);
      }
    }
    /*
    Source gotten from: https://www.valentinog.com/blog/html-table/
    */
    function generateTable(table, data) {
      for (let element of data) {
        let row = table.insertRow();
        for (key in element) {
          let cell = row.insertCell();
          let text = document.createTextNode(element[key]);
          cell.appendChild(text);
        }
      }
    }





    /*
    Turn into JQUERY less code soon.
    */

    function getProgress(url) {
        $.ajax({
            url: url,
            data: {ajax: "progress"},
            type: 'get'
        })
            .done(function (data) {
                $("#progress").html('<pre id=\'consoleoutput\' style=\'white-space: pre-wrap; text-align:left; height: 300px; max-height: 300px; overflow:auto; color:#fff;\'>' + data + '</pre>');
            })

    };
    function logScroll() {
        var d = $('#consoleoutput');
        d.scrollTop(d.prop("scrollHeight"));
    }

    //On Click Prepare unpack composer
    $(document).ready(function () {
        $(document).on("click", "#prepare1btn", function () {
            $("#progressdiv").html(setup);
            return $.post(ajaxurl, {ajax: "prepare1"});
        })
    });
    //On Click Flarum
    $(document).ready(function () {
        $(document).on("click", "#flarumbtn", function () {
            $("#progressdiv").html(waiting);
            return $.post(ajaxurl, {ajax: "install"});
        })
    });
    //On Click Cleanup
    $(document).ready(function () {
        $(document).on("click", "#cleanupbtn", function () {
            $("#progressdiv").html('<button class="instal1 btn btn-default btn-lg" disabled>Removing Installer<i class="fa fa-cog fa-spin"></i></button>');
            return $.post(ajaxurl, {ajax: "cleanup"})
                .done(function() {
                    window.setTimeout(window.location.href = "./",10000);
                });
        })
    });
