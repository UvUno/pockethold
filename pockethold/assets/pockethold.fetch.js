    // Run on page load.
    initFunction();

    /*

    */
    //Run on first visit/page refresh
    function initFunction() {
        const statusreply = await fetch(fetchurl + '?ajax=status');

        if (statusreply.status = 'prepare') {
          //  block of code to be executed if condition1 is true
        } else if (statusreply.status = 'waiting') {
          //  block of code to be executed if the condition1 is false and condition2 is true
        } else if (statusreply.status = 'cleanup') {
          //  output cleanup button
        } else if (statusreply.status = 'reqnotmet'){
          // Out put what fails
          // Output Download logs for issue creation
        } else {


        }


    }
    //Run when status is 'waiting'
    function waitingFunction() {

    }
    //run when status is install
    function installFunction() {

    }
    function cleanupFunction() {

    }




    /*
    function status(url) {
      await statusreply fetch(fetchurl + '?ajax=status')
        timer = setTimeout(function () {
          fetch(fetchurl + '?ajax=status')
            .then(data)
            .then(function (data) {
                    $("#progressdiv").html(window[data]);
                    if (data === "waiting"){
                        getProgress(url);
                        setInterval(logScroll,1000);
                    }
                    status(url);
                })
        }, 5000);
    };

    i

    fetch(fetchurl + '?ajax=status')
      .then((response) => {
        return response.json();
      })
      .then((myJson) => {
        console.log(myJson);
      });



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

    /*
    function status(url) {
        timer = setTimeout(function () {
            $.ajax({
                url: url,
                data: {ajax: "status"},
                type: 'get'
            })
                .done(function (data) {
                    $("#progressdiv").html(window[data]);
                    if (data === "waiting"){
                        getProgress(url);
                        setInterval(logScroll,1000);
                    }
                    status(url);
                })
        }, 5000);
    };
    */

    // Runs at startup.
    setInterval(status(ajaxurl),5000);

    //Prepare Button Click Events
    $(document).ready(function () {
      document.getElementById("#prepare1btn").addEventListener("click", getUIContent(prepare1));
      document.getElementById("#flarumbtn").addEventListener("click", getUIContent(install));
      document.getElementById("#cleanupbtn").addEventListener("click", getUIContent(waiting));
    });


    //Update UI elements
    function getUIContent(uiElement, ajaxRequest) {
      document.getElementById("#progressdiv").innerHTML = uiElement;
      return fetch(ajaxurl+'?ajax='+ajaxRequest) .then(
        function(response) {

        }

      )

    }

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
