    /*
     * Requires jQuery 3
     * */

    var ajaxurl = window.location.href;
    var timer;
    var count = 0;
    var fmsg = "<h2 class='instal1'>Install failed</h2>";

    var waiting = "<button class='instal1 btn btn-default btn-lg' disabled>Working Please wait<i class='fa fa-cog fa-spin'>" +
        "</i></button><div class='card' style='margin-top: 50px;'><div class='card-header'>Current Task Log </div>" +
        "<div class='card-body' id='progress' style=' padding:5px; background: #141414;'></div></div>";


    var setup = "<button class='instal1 btn btn-default btn-lg' disabled>Getting Composer <i class='fa fa-cog fa-spin'></i></button>";

    var flarum = '<span id="flarumbtn" class="instal1 btn btn-primary btn-lg" role="button">Step 1: Download Flarum</span>';
    var bazaar = '<span class="instal1"><span id="bazaarbtn" class="cleanup btn btn-primary btn-lg" role="button">Step 2: Download Bazaar</span></span>';
    var cleanup = '<span id="cleanupbtn" class="instal1 btn btn-primary btn-lg" role="button">Step 3: Start Flarum Installer</span>';

    function getProgress(url) {
        $.ajax({
            url: url,
            data: {ajax: "progress"},
            type: 'get'
        })
            .done(function (data) {
                $("#progress").html('<pre id=\'consoleoutput\' style=\'white-space: pre-wrap; text-align:left; height: 300px; max-height: 300px; overflow:auto; color:#fff;\'>'data'</pre>');
            })

    };
    function logScroll() {
        var d = $('#consoleoutput');
        d.scrollTop(d.prop("scrollHeight"));
    }
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
    // Runs at startup.
    setInterval(status(ajaxurl),5000);

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
            return $.post(ajaxurl, {ajax: "flarum"});
        })
    });
    //On Click Bazaar
    $(document).ready(function () {
        $(document).on("click", "#bazaarbtn", function () {
            $("#progressdiv").html(waiting);
            return $.post(ajaxurl, {ajax: "bazaar"});
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
