var m = require("mithril")

var init = require("./views/Init")
m.route(document.getElementById("pockethold"), "/init", {
    "/init": init,
})

require("./models/getStatus")
getStatus();
