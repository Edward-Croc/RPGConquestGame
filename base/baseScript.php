

<script>
    // Function to handle the "End Turn" button click
    document.addEventListener("DOMContentLoaded", function () {
        const endTurnButton = document.getElementById("endTurnButton");
        const endTurnCounter = document.getElementById("endTurnCounter");

        // Add an event listener for the button click
        endTurnButton.addEventListener("click", function () {
            // Create a new XMLHttpRequest
            const xhr = new XMLHttpRequest();
            xhr.open("POST", "../mechanics/endTurn.php", true);

            // Handle the response
            xhr.onload = function () {
                if (xhr.status === 200) {
                    // Update the counter display with the response
                    endTurnCounter.textContent = "End Turn Count: " + xhr.responseText;
                } else {
                    console.error("Failed to update end turn counter.");
                }
            };

            // Send the request
            xhr.send();
        });
    });

    function toggleSidebar() {
        document.getElementById("sidebar").classList.toggle("active");
    }

    function toggleDescription(id) {
        const description = document.getElementById('description-' + id);
        if (description.style.display === 'none') {
            description.style.display = 'block';
        } else {
            description.style.display = 'none';
        }
    }

    function toggleInfo(id) {
        const info = document.getElementById('info-' + id);
        if (info.style.display === 'none') {
            info.style.display = 'block';
        } else {
            info.style.display = 'none';
        }
    }
</script>