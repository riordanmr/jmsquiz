<?php
// Web app to administer a quiz. 
// The application has these pieces, corresponding to various values
// of the "a" (action) query string parameter.
// - A login page in which you provide your "client id" (no password).
//   This is displayed when no value for "a" is provided.
// - A page which shows all the questions, and controls for entering your answers.
//   Displayed when a=show.
// - An endpoint which receives periodic JSON updates from the browser, when a user
//   types answer text.
//   Activated when a=update.
// - A page which displays the questions and your answers in printer-ready format.
//   Displayed when a=print.
// - A secret page which lists all users who have answers on file.
//   Displayed when a=listclients.
//
// Mark Riordan  riordan@rocketmail.com  2023-09-29

// Include the database configuration file
include_once("../../jmsquiz.config.php"); // Replace with the actual path to db_config.php

function getPostedClientId() {
    // Fetch jmsid from POSTed form.
    $id = $_POST['jmsid'];
    // Strip any non-alphanumeric chars.
    $id = preg_replace("/[^a-zA-Z0-9]/", "", $id);
    return $id;
}

function connectDb() {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USERNAME, DB_PASSWORD);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
    return $pdo;
}

// Return an array of answers from the database.
// The array is indexed starting at 1.
function getArrayOfAnswers($pdo, $jmsid) {
    $answers = array();
    $sql = "SELECT * FROM answers WHERE jmsid='$jmsid';";
    try {
        $stmt = $pdo->query($sql);
        if($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // We did fetch the answers for this user.
            for($j=1; $j<=30; $j++) {
                $answers[$j] = $row['a' . $j];
            }
        } else {
            // This user's answers are not in the DB, so create a record for them.
            $sql = "INSERT INTO answers (jmsid) VALUES (:jmsid)";
            try {
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':jmsid', $jmsid, PDO::PARAM_STR);
                $stmt->execute();
                for($j=1; $j<=30; $j++) {
                    $answers[$j] = '';
                }
            } catch (PDOException $e) {
                die("Error: " . $e->getMessage());
            }
        }
    } catch (PDOException $e) {
        die("Query failed: " . $e->getMessage());
    }
    return $answers;
}

$action = 'login';
if (isset($_GET['a'])) {
    $action = $_GET['a'];
}
if($action == 'update') {
    // Process JSON request to update an answer.

    // Retrieve the raw POST data
    $jsonData = file_get_contents('php://input');
    //error_log("Got JSON: $jsonData");

    // Decode the JSON data into a PHP associative array
    $data = json_decode($jsonData, true);

    // Check if decoding was successful
    if ($data !== null) {
        $pdo = connectDb();
        // Access the data and perform operations
        $clientId = $data['clientId'];
        $ansId = $data['ansId'];
        $ansText = $data['ansText'];
        // Perform further processing or respond to the request
        $sql = "UPDATE answers SET $ansId = :ansText WHERE jmsid=:jmsid;";
        //error_log($sql);
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':jmsid', $clientId, PDO::PARAM_STR);
            $stmt->bindParam(':ansText', $ansText, PDO::PARAM_STR);
            $stmt->execute();
            header('Content-Type: application/json; charset=utf-8');
            echo '{"status": "ok"}';
        } catch (PDOException $e) {
            http_response_code(401);
            echo '{"status": "' . htmlentities($e->getMessage()) . '"}';
        }
        $pdo = null;
    } else {
        // JSON decoding failed
        http_response_code(400); // Bad Request
        echo "Invalid JSON data";
    }
} else {
    // All actions other than update should show this HTML head section.
?><!DOCTYPE html>
<html>
<head>
    <title>JMS Partners Exercise</title>
    <style>
        body {
            font-family: Charter, PT Serif, Georgia;
            font-size: 14pt;
        }
        h1 {font-family: sans-serif; font-size: 20pt;}
        h2 {font-family: sans-serif; font-size: 16pt;}
        .jmsid {font-family: Menlo, Consolas, Courier New; font-size: 14pt;
            border-width: 1px;
            box-sizing: border-box;
            border-style: solid;
        }
        .jmsbutton {font-family: sans-serif; font-size: 14pt;
            color: #333;
            box-shadow: -3px 3px orange, -2px 2px orange, -1px 1px orange;
            border: 1px solid orange;
        }
        .button-3d {
            background-color: #c0f0ff;
            transition: all 0.03s linear;
            font-family: sans-serif; font-size: 14pt;
        }

        .button-3d:hover {
            background-color: #65b9e6;
        }

        .button-3d:active {
            box-shadow: 0 0 2px darkslategray;
            transform: translateY(2px);
        }

        .qcolumn {
            text-align: right;
            vertical-align: top;
        }

        .answerbox {
            font-family: sans-serif; font-size: 14pt;
        }
        .vspace {
            /* This is used to modify the height of a <br/> element. */
            display: block; /* makes it have a width */
            content: ""; /* clears default height */
            margin-top: 4pt; /* change this to whatever height you want it */
        }
        tr.printpadding>td {
            padding-bottom: 6pt;
        }

        .last_update_div {
            position: fixed;
            bottom: 0;
            right: 0;
        }
        .last_update_p {
            text-align: right;
            font-family: Arial Narrow; 
            font-size: 10pt;
            color: grey;
            width: 12em;
            position: absolute;
            bottom: 0;
            right: 0;
        }
        /* Use a larger font, only on mobile devices.  Thanks to https://habr.com/en/sandbox/163605/ */
        @media (pointer: coarse)  {
            /* mobile device */
            body {
                font-size: 36pt;
            }
            h1 {font-size: 40pt;}
            h2 {font-size: 30pt;}
            .stuname {
                font-size: 30pt;
            }
            .jmsid {
                font-size: 24pt;
            }
            .answerbox {
                font-size: 32pt;
            }
            .last_update_p {
                font-size: 18pt;
            }
            .button-3d {
                font-size: 26pt;
            }
            textarea {
                -webkit-box-sizing: border-box;
                -moz-box-sizing: border-box;
                box-sizing: border-box;

                width: 100%;
            }
        }        
    </style>
    <script>
        function onSubmitEnter() {
            var clientid = document.getElementById('jmsid').value;
            var ok = clientid.length > 0;
            if(ok) {
                document.mainform.submit();
            }
            return ok;
        }
        function submitForm(formId) {
            let form = document.getElementById(formId);
            form.submit();
        }
        var curQNum=0;
        const answers = [];
        function sendAnswerUpdate(id, myAnsText) {
            let myJmsid = document.getElementById('jmsid').innerHTML;
            console.log('sendAnswerUpdate: jmsid=' + myJmsid + " myAnsText=" + myAnsText);
            let obj = {
                clientId: myJmsid,
                ansId: id,
                ansText: myAnsText
            };
            let json = JSON.stringify(obj, null, 2);
            console.log('sendAnswerUpdate: ' + json);
            fetch('index.php?a=update', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: json
            })
            .then((response)=>response.json())
            .then((obj)=> {
                // At this point, obj is a JS object with field status set to "ok".
                // var msg='';
                // for(idx in obj) {msg += " " + idx + '=' + obj[idx];}
                // console.log("Update POST returned " + msg)
                if(obj.hasOwnProperty('status')) {
                    if(obj.status=='ok') {
                        // This answer was updated successfully on the server.
                        // Display the time last updated at the bottom right.
                        var d = new Date();
                        var txtDate = d.toLocaleString();
                        var msg = "Updated " + txtDate;
                        document.getElementById("last_update").innerHTML = msg;
                    } else {
                        alert("Error!  Server said: " + obj.status);
                    }
                } else {
                    alert("Error!  Web server rejected update request. \nApparent bug in application");
                }
            });
        }
        function onFocus(id) {
            let ansText = document.getElementById(id).value;
            var qnum = id.substr(1);
            answers[qnum] = ansText;
            curQNum = qnum;
            console.log("onFocus(" + id + "): answers[" + qnum + "]=" + answers[qnum]);
        }
        function onBlur(id) {
            var qnum = id.substr(1);
            let ansText = document.getElementById(id).value;
            console.log("onBlur(" + id + ") ansText="+ansText + " answers[qnum]=" + answers[qnum]);
            if(answers[qnum] !== ansText) {
                answers[qnum] = ansText;
                sendAnswerUpdate(id,ansText);
            }
        }
    </script>
</head>
<body>
<?php
if($action=='login') {
    // Show the "login" page, which prompts for the client ID.
?>
    <h1>JMS Partners Career Exploration Exercise</h1>
    <p>To start or resume the exercise, enter your 27-digit JMS Partners client ID below
    and click on Enter Exercise. <br/>
    If you do not have a client ID, or cannot remember it, you may use your initials.
    However, in this case, confidentiality cannot be guaranteed.
    </p>
    <form id='mainform' name='mainform' action="index.php?a=show" method="POST" onsubmit="event.preventDefault(); return onSubmitEnter();">
        Client ID:
            <input id="jmsid" class="jmsid" type="text" name="jmsid" size="27">
            <input class="button-3d" type="submit" name="submitbutton" value="Enter Exercise">
    </form>
<?php
} else if($action=='show') {
    // Show the form with the questions.
    $jmsid = getPostedClientId();
?>
<span style="float:right">Client <span id='jmsid'><?php echo $jmsid;?></span></span>
<h1>JMS Partners Career Exploration Exercise</h1>
<h2>SENTENCE COMPLETIONS</h2>
<p>Complete each of the sentences below with the first response that
    comes to mind. Avoid too much consideration of any single item.
</p>
<p>Your answers will be saved as you work, so there is no need to "submit"
    the results. You can come back later to resume the exercise if you like.
</p>
<div class="last_update_div">
    <!-- stuff -->
    <p id="last_update" class="last_update_p"></p>
</div>
<table>

<?php
    $pdo = connectDb();
    $answers = getArrayOfAnswers($pdo, $jmsid);

    // Fetch the questions and create the HTML displaying them.
    $sql = "SELECT qnum, qtext FROM questions ORDER BY qnum";
    try {
        $stmt = $pdo->query($sql);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $qnum = $row['qnum'];
            $qtext = $row['qtext'];

            // Display this question and a textarea for the answer.
            echo "<tr><td class='qcolumn'>$qnum.</td>";
            echo "<td>$qtext<br/>";
            echo "<textarea id='a$qnum' class='answerbox' rows='4' cols='80' onfocus='onFocus(\"a$qnum\");' onblur='onBlur(\"a$qnum\");'>$answers[$qnum]</textarea>";
            echo "</td></tr>\n";
        }
    } catch (PDOException $e) {
        die("Query failed: " . $e->getMessage());
    }

    // Close the database connection
    $pdo = null;
    // Display a button to get to the printer-friendly page.
    $jmsid = getPostedClientId();
    echo "<tr><td></td><td><br/>";
    echo '<form action="index.php?a=print" method="POST">';
    echo '<input type="hidden" name="jmsid" value="' . $jmsid . '">';
    echo '<input type="submit" class="button-3d" value="Show Printable Results">';
    echo '</form></td></tr>';
} else if($action=='listclients') {
    // Display a page listing the people who have taken the quiz.
?>
    <h1>List of clients who have done the exercise</h1>
<?php
    $pdo = connectDb();
    $sql = "SELECT jmsid FROM answers ORDER BY jmsid;";
    echo "<table>";
    try {
        $stmt = $pdo->query($sql);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $clientid = $row['jmsid'];
            echo "<tr><td><form id='form$clientid' action='index.php?a=print' method='POST'>";
            echo '<input type="hidden" name="jmsid" value="' . $clientid . '">';
            echo "<a onclick='submitForm(\"form$clientid\")' href='#'>$clientid</a></form></td></tr>\n";
        }
    } catch (PDOException $e) {
        die("Query failed: " . $e->getMessage());
    }

    // Close the database connection
    $pdo = null;
} else if($action=='print') {
    // Display a page listing the questions and answers in a printer-friendly format.
    $jmsid = getPostedClientId();
?>
    <span style="float:right">Client <span id='jmsid'><?php echo $jmsid;?></span></span>
    <h1>JMS Partners Career Exploration Exercise</h1>
    <table>
<?php    
    $pdo = connectDb();
    $answers = getArrayOfAnswers($pdo, $jmsid);

    // Fetch the questions and create the HTML displaying them.
    $sql = "SELECT qnum, qtext FROM questions ORDER BY qnum";
    try {
        $stmt = $pdo->query($sql);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $qnum = $row['qnum'];
            $qtext = $row['qtext'];

            // Display this question and the answer.
            echo "<tr class='printpadding'><td class='qcolumn'>$qnum.</td>";
            echo "<td><span>" . htmlentities($qtext) . ":</span> ";
            $ans = htmlentities($answers[$qnum]);
            $ans = str_replace("\n","<br class='vspace'/>", $ans);
            echo "<span class='answerbox'>" . $ans . "</span>";
            echo "</td></tr>\n";
        }
        // Create buttons to return to editing and go home.
        echo "<tr><td></td><td><br/>";
        echo '<form action="index.php?a=show" style="display:inline" method="POST">';
        echo '<input type="hidden" name="jmsid" value="' . $jmsid . '">';
        echo '<input type="submit" class="button-3d" value="Edit Answers">';
        echo '</form>';
        echo ' &nbsp; &nbsp; ';
        echo ' <form action="index.php?a=show" method="GET" style="display:inline">';
        echo '<input type="submit" class="button-3d" value="Home">';
        echo '</form>';
        echo '</td>';
        echo '</tr>';
    } catch (PDOException $e) {
        die("Query failed: " . $e->getMessage());
    }

    // Close the database connection
    $pdo = null;

} // end of testing for various values of action
?>
</table>
</body>
</html>
<?php
} // end of testing for $action==update
?>
