/*
 * Requires Jquery 3
 * */

var ajaxurl = 'ajax.php';
var timer;
var count = 0;
var dmsg = "<h2 class='instal1'>Please Wait</h2>";
var fmsg = "<h2 class='instal1'>Install failed</h2>";
var preparebtn = '<span class="instal1"><span id="preparebtn" class="instal1 btn btn-primary btn-lg" role="button">Step 1: Prepare</span></span>';
var composerbtn = '<span id="composerbtn" class="instal1 btn btn-primary btn-lg" role="button">Step 2: Install</span>';
var bazaarbtn = '<span class="instal1"><span id="cleanupbtn" class="cleanup btn btn-primary btn-lg" role="button">Step 3: Finish</span><span id="bazaarbtn" class="btn btn-lg" role="button">Install Bazaar</span></span>';
var cleanupbtn = '<span id="cleanupbtn" class="instal1 btn btn-primary btn-lg" role="button">Step 3: Finish</span>';

function poll(url, equalname, replacewith1, dmsg, fmsg) {
    timer = setTimeout(function () {
        $.ajax({
            url: url,
            data: {ajax: "status"},
            type: 'get'
        })
            .done(function (data) {
                if (data === equalname) {
                    $(".instal1").replaceWith(replacewith1);
                    $("#progressdiv").replaceWith('<div id="progressdiv"></div>');
                    count = 0;
                    if (data === 'waiting1') {
                        prog(url);
                    }
                }
                else {
                    if (++count > 110) {
                        $(".instal1").replaceWith(fmsg);
                        $("#progressdiv").replaceWith('<div id="progressdiv">Timed out, or failed. Check logs.</div>');
                    }
                    else {
                        if (data === 'waiting1') {
                            prog(url);
                        }
                        $(".instal1").replaceWith(dmsg);
                        poll(url, equalname, replacewith1, dmsg, fmsg);

                    }
                }
            })
    }, 10000)
};

function prog(url) {
    $.ajax({
        url: url,
        data: {ajax: "progress"},
        type: 'get'
    })
        .done(function(result) {
            $("#progressdiv").replaceWith('<div id="progressdiv">Progress: ' + result + ' out of 87</div>');

        }).fail(function() {
    });
};


//Actual commands
// Runs at startup.
$(document).ready(function () {
    $.ajax({
        url: ajaxurl,
        data: {ajax: "status"},
        type: 'get'
    })
        .done(function (res) {
            console.log(res);
            if (res === 'prepare') {
                $(".instal1").replaceWith(preparebtn);
            } else if (res === 'composer') {
                $(".instal1").replaceWith(composerbtn);
            } else if (res === 'cleanup1') {
                $(".instal1").replaceWith(bazaarbtn);
            } else if (res === 'cleanup2') {
                $(".instal1").replaceWith(cleanupbtn);
            } else if (res === 'waiting1') {
                $(".instal1").replaceWith('<h2 class="instal1">Flarum is downloading!</h2>');
                poll(ajaxurl, 'cleanup1', bazaarbtn, dmsg, '');
            } else if (res === 'waiting2') {
                $(".instal1").replaceWith('<h2 class="instal1">Bazaar is being installed!</h2>');
                poll(ajaxurl, 'cleanup2', cleanupbtn, dmsg, '');
            }
        })
        .fail(function (err) {
            console.log('Error: ' + err.status);
            $(".install").replaceWith('<h2 class="instal1">Error:' + err.status + '</h2>');
        });


});

//On Click Prepare
$(document).ready(function () {
    $(document).on("click", "#preparebtn", function () {
        $(".instal1").replaceWith('<h2 class="instal1">Downloading Composer</h2>');
        poll(ajaxurl, "composer", composerbtn, dmsg, '');
        return $.post(ajaxurl, {ajax: "prepare"});
    })
});
//On Click Composer
$(document).ready(function () {
    $(document).on("click", "#composerbtn", function () {
        $(".instal1").replaceWith('<h2 class="instal1">Downlading Flarum</h2>');
        poll(ajaxurl, "cleanup1", bazaarbtn, dmsg, '');
        return $.post(ajaxurl, {ajax: "composer"});
    })
});
//On Click Bazaar
$(document).ready(function () {
    $(document).on("click", "#bazaarbtn", function () {
        $(".instal1").replaceWith('<h2 class="instal1">Installing Bazaar</h2>' );
        poll(ajaxurl, 'cleanup2', cleanupbtn, dmsg, '');
        return $.post(ajaxurl, {ajax: "bazaar"});
    })
});
//On Click Composer
$(document).ready(function () {
    $(document).on("click", "#cleanupbtn", function () {
        $(".instal1").replaceWith('<h2 class="instal1">Removing Installer</h2>');
        return $.post(ajaxurl, {ajax: "cleanup"})
            .done(function() {
                window.setTimeout(window.location.href = "./",10000);
            });
    })
});