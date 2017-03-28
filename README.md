# Web Installer/Downloader for Flarum Beta

The goal is to have it download composer, then download latest beta for flarum, run composer install, and then, recurcivly delete any trace of composer, and unlink itself while redirecting to the flarum setup script.

When the above work, the plan is to add flagrows bazar script as an optional install parameter, which in turn will allow flarum to work 100% without shell, except for updates obviously..

Or at least that is what I hope :P 


Credits (If it actually works): 
https://codedump.io/share/f5W5vnPOI18q/1/run-composer-with-a-php-script-in-browser
