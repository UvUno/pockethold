    /*
     * Requires jQuery 3
     * */

    var fetchurl = window.location.href;
    var timer;
    var count = 0;
    var fmsg = "<h2 class='instal1'>Install failed</h2>";

    var waiting = "<button class='instal1 btn btn-default btn-lg' disabled>Working Please wait<i class='fa fa-cog fa-spin'>" +
        "</i></button><div class='card' style='margin-top: 50px;'><div class='card-header'>Current Task Log </div>" +
        "<div class='card-body' id='progress' style=' padding:5px; background: #141414;'></div></div>";


    var setup = "<button class='instal1 btn btn-default btn-lg' disabled>Getting Composer <i class='fa fa-cog fa-spin'></i></button>";

    var flarum = '<span id="flarumbtn" class="instal1 btn btn-primary btn-lg" role="button">Step 1: Download Flarum</span>';
    var cleanup = '<span id="cleanupbtn" class="instal1 btn btn-primary btn-lg" role="button">Step 2: Start Flarum Installer</span>';
    var notpossible = '<span>Error: Server requirements not met</span><table></table>';
