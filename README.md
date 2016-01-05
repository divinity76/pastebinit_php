# pastebinit_php
php script to upload stuff to pastebin websites - inspired by Stéphane Graber's pastebinit

as of writing, it only works with Fedora's pastebin ( http://paste.fedoraproject.org/ ), takes everything in stdin, and upload it, takes NO arguments, and uploads with settings ['paste_lang'=>'text','mode'=>'json','private_paste'=>'yes','paste_expire' => 1 * 60 * 60 * 24 * 365] (past_expire means 1 year), and returns the wget friendly URL.
