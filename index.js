document.addEventListener("DOMContentLoaded", () => {
    // Tab handling
    const tabs = document.querySelectorAll(".tab");
    const boxes = document.querySelectorAll(".box");

    // Get the saved tab ID from localStorage
    const savedTabId = localStorage.getItem("activeTab");
    let tabToActivate = null;

    // Find the tab element based on the saved ID
    if (savedTabId) {
        const potentialSavedTab = document.getElementById(savedTabId);
        if (potentialSavedTab && potentialSavedTab.classList.contains("tab")) {
            tabToActivate = potentialSavedTab;
        }
    }

    // Default to the first tab if no saved tab or invalid saved ID
    if (!tabToActivate && tabs.length > 0) {
        tabToActivate = tabs[0];
    }

    // Activate the determined tab and corresponding box
    tabs.forEach(t => t.classList.remove("active"));
    boxes.forEach(box => box.classList.remove("active"));

    if (tabToActivate) {
        tabToActivate.classList.add("active");
        const boxId = tabToActivate.id.replace("tab", "box");
        const boxToActivate = document.getElementById(boxId);
        if (boxToActivate) {
            boxToActivate.classList.add("active");
        }
        localStorage.setItem("activeTab", tabToActivate.id);
    }

    // Add click listeners for tab switching
    tabs.forEach(tab => {
        tab.addEventListener("click", () => {
            tabs.forEach(t => t.classList.remove("active"));
            boxes.forEach(box => box.classList.remove("active"));

            tab.classList.add("active");
            const boxId = tab.id.replace("tab", "box");
            const boxToActivate = document.getElementById(boxId);
            if (boxToActivate) {
                boxToActivate.classList.add("active");
            }
            localStorage.setItem("activeTab", tab.id);
        });
    });


    // Autofill username based on IP
    const form = document.getElementById("chatForm");
    const usernameInput = document.getElementById("usernameInput");
    const submitBtn = form.querySelector("button[type='submit']");

    fetch('check_user.php')
        .then(res => res.json())
        .then(data => {
            if (data.username) {
                usernameInput.value = data.username;
                usernameInput.readOnly = true;
                submitBtn.disabled = false;
            }
        });

    // Check for duplicate usernames
    usernameInput.addEventListener('input', () => {
        const username = usernameInput.value.trim();
        if (username.length === 0) {
            submitBtn.disabled = true;
            return;
        }

        fetch(`check_user.php?check=${encodeURIComponent(username)}`)
            .then(res => res.json())
            .then(data => {
                if (data.taken) {
                    submitBtn.disabled = true;
                    usernameInput.setCustomValidity("This username is already taken.");
                } else {
                    submitBtn.disabled = false;
                    usernameInput.setCustomValidity("");
                }
            });
    });

    // Form submit
    form.addEventListener("submit", async (e) => {
        e.preventDefault();

        const formData = new FormData(form);
        const recaptchaResponse = grecaptcha.getResponse();

        if (!recaptchaResponse) {
            alert("Please complete the reCAPTCHA.");
            return;
        }

        formData.append("g-recaptcha-response", recaptchaResponse);

        try {
            const response = await fetch("", {
                method: "POST",
                body: formData
            });

            const text = await response.text();
            console.log("Server response:", text); // Log the server response for debugging

            if (text.includes("✅ Message sent!")) {
                alert("✅ Message sent!");
                
                window.location.reload(); // Refresh the page after successful submission
                form.reset();
                grecaptcha.reset();
                document.getElementById("chatIframe").contentWindow.location.reload();
            } else if (text.includes("⚠️") || text.includes("❌")) {
                alert(text);
            } else {
                alert("⚠️ Unexpected response.");
                console.log("Unexpected response:", text); // Log unexpected responses
            }
        } catch (err) {
            console.error("Submission error:", err);
            alert("❌ There was an error sending your message.");
        }
    });
});

// Function to toggle the visibility of the mascot image
function imageToggle() {
    const mascotImage = document.getElementById("mascotImage");
    if (mascotImage) {
        mascotImage.style.display = mascotImage.style.display === "none" ? "block" : "none";
    }
}