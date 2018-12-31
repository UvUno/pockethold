var m = require("mithril")

var status = require("./views/status")
var test = require("./views/test")

m.route(document.getElementById("pockethold"), "/test", {
    "/status": status,
	"/test": test,
})

