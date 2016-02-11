Self-service "false positive" (blocked email) report and release system for Halon's email gateway. Please read more on http://wiki.halon.io and http://halon.io

Description
-----------

We normally recommend our customers to reject spam, because the accuracy of the system is high enough to handle the few FPs (genuine emails detected as spam). By rejecting spam with a helpful message, possibly containing a link where the incident can be reported to your support, FPs can be identified and taken action upon by the sender.

However, in order to further reduce the support burden, this adaptive quarantine can be used. Similar to our "reject recommendation", it's the sender that initiate the release process, after having passed a CAPCHA test. The recipient is notified via an automated email, with a link to release the message. Since the sender is notified of the blocked email immediately, un-reported spam can be retained during a very short period (for example 1 day). Once reported however, the retention time is extended (to for example 1 week), giving the recipient plenty of time to check the inbox, find the report, and release the blocked email.

Halon installation
------------------

The most convenient way to implement this report-release logic on the Halon (MTA) side is probably to use a separate transport (queue template) for quarantining the spam. It allows you to efficiently filter out those email, when working with the queue (because `$transportid` is indexed in the database, and available as a http://wiki.halon.io/Search_filter field).

Begin by creating a mail transport with a meaningful name such as "Quarantine" or "sender-fp-release".

Add the following code to the DATA flow (or an include file) or some variant of it, and replace " X" with the transport ID:

```
function Reject($msg) {
        ...
        if (GetAttachmentSize("/")[0] < 10*1024*1024) {
                SetDelayedDeliver(3600*24);
                SetMailTransport("mailtransport:X"); // Quarantine
                CopyMail();
                $node = explode(".", gethostname())[0];
                $msg .= " Release at https://release.example.com/?msgid=$messageid&node=$node";
        }
        builtin Reject($msg);
}
```

Finally add the following code to the pre-delivery script, again replacing "X" and the API URL:

```
if ($transportid == "mailtransport:X") {
        // Quarantine
        $queuetime = time() - $receivedtime;
        $node = explode(".", gethostname())[0];
        $opt = ["timeout" => 5, "connect_timeout" => 5, "ssl_default_ca" => true, "ssl_verify_host" => true];
        $get = [$queueid, $node];
        $res = http("https://release.example.com/api.php?apikey=secret&type=status&queueid=$1&node=$2", $opt, $get);
        $res = json_decode($res);
        if (!isset($res["status"])) {
                // API error
                Reschedule(3600, ["reason" => "Invalid API response", "increment_retry" => false]);
        } else if ($res["status"] == "release") {
                // Recipient released, fall-through to routing
        } else if ($queuetime > 3600*24 and $res["status"] == "delete") {
                // At least 1 day has passed, sender didn't report yet
                Delete();
        } else if ($queuetime > 3600*24*7) {
                // At least 7 days has passed, recipient didn't release yet
                Delete();
        } else {
                Reschedule(3600*24, ["reason" => "Keep in quarantine", "increment_retry" => false]);
        }
}
```
