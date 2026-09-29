<?php


// =====================================================
// SET AUTHENTICATION MESSAGE
// =====================================================

function setAuthenticationMessage(
    string $messageType,
    string $messageText
): void {

    $_SESSION["authentication_message"] = [

        "type" => $messageType,

        "message" => $messageText

    ];

}


// =====================================================
// DISPLAY AUTHENTICATION MESSAGE
// =====================================================

function displayAuthenticationMessage(): void {

    if (
        !isset($_SESSION["authentication_message"])
    ) {

        return;

    }


    $messageType =
        $_SESSION["authentication_message"]["type"]
        ?? "error";


    $messageText =
        $_SESSION["authentication_message"]["message"]
        ?? "Something went wrong.";


    // -------------------------------------------------
    // ICON
    // -------------------------------------------------

    $messageIcon = "bi-exclamation-circle";


    if ($messageType === "success") {

        $messageIcon = "bi-check-circle";

    }

    elseif ($messageType === "warning") {

        $messageIcon = "bi-exclamation-triangle";

    }


    // -------------------------------------------------
    // CSS CLASS
    // (the stylesheet uses "alert-danger" for errors,
    // but our message type is called "error")
    // -------------------------------------------------

    $messageCssClass = ($messageType === "error") ? "danger" : $messageType;


    // -------------------------------------------------
    // DISPLAY MESSAGE
    // -------------------------------------------------

    echo '

        <div class="authentication-alert alert-' .
        htmlspecialchars($messageCssClass) .
        '">

            <div class="authentication-alert-icon">

                <i class="bi ' .
                $messageIcon .
                '"></i>

            </div>


            <div class="authentication-alert-message">

                ' .
                htmlspecialchars($messageText) .
                '

            </div>


            <button
                type="button"
                class="authentication-alert-close"
                onclick="this.parentElement.remove()"
            >

                &times;

            </button>

        </div>

    ';


    // -------------------------------------------------
    // REMOVE MESSAGE AFTER DISPLAY
    // -------------------------------------------------

    unset(
        $_SESSION["authentication_message"]
    );

}

?>