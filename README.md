Self-service "false positive" (blocked email) report and release system for Halon's email gateway. Please read more on http://wiki.halon.io and http://halon.io

Description
-----------

We normally recommend our customers to reject spam, because the accuracy of the system is high enough to handle the few FPs (genuine emails detected as spam). By rejecting spam with a helpful message, possibly containing a link where the incident can be reported to your support, FPs can be identified and taken action upon by the sender.

However, in order to further reduce the support burden, this adaptive quarantine can be used. Similar to our "reject recommendation", it's the sender that initiate the release process, after having passed a CAPCHA test. The recipient is notified via an automated email, with a link to release the message. Since the sender is notified of the blocked email immediately, un-reported spam can be retained during a very short period (for example 1 day). Once reported however, the retention time is extended (to for example 1 week), giving the recipient plenty of time to check the inbox, find the report, and release the blocked email.

Installation
------------

1. Run the SQL queries listed in the text file for the database type you want to use to create the necessary tables
2. Copy the `/settings-default.php` file to `/settings.php` and open it to change the settings.

Halon integration
------------------

Begin by creating two quarantines with comments such as "Sender FP release - short" and "Sender FP release - long".

Add the following code to the DATA flow (or an include file) or some variant of it, and replace "X" with the quarantine ID of the short one:

```
function Reject($msg) {
        ...
        if (MIME("0")->getSize() < 10*1024*1024) {
                global $messageid;
                builtin Quarantine("mailquarantine:X", ["done" => false, "reject" => false]);
                $node = explode(".", gethostname())[0];
                $msg .= " Release at https://release.example.com/?msgid=$messageid&node=$node";
        }
        builtin Reject($msg);
}
```

and add the following API script, where you replace "XXX" with the SHA1 hash of the password from settings.php: 

```
if ($username == "reportfp" and sha1($password) == "XXX") {
        if ($soapcall == "mailQueue") Authenticate();
        if ($soapcall == "mailQueueRetry") Authenticate();
        if ($soapcall == "mailQueueUpdateBulk") Authenticate();
}
```
