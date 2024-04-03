<?php
$photoId = $_GET['id'];

// Database connection configuration
$host = "localhost";
$username = "root";
$password = "";
$database = "db_photo";

// Establish database connection
$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Retrieve the photo path from the database based on the photo ID
$stmt = $conn->prepare("SELECT path FROM photos WHERE id = ?");
$stmt->bind_param("i", $photoId);
$stmt->execute();
$stmt->store_result(); // Store the result set
$stmt->bind_result($photoPath);

if ($stmt->fetch()) {
    // Construct the full path to the photo
    $fullPath = $photoPath;

    // Retrieve the annotations associated with the photo from the database
    $annotationsQuery = "SELECT * FROM annotations";
    $annotationsStmt = $conn->prepare($annotationsQuery);
    $annotationsStmt->execute();
    $annotationsStmt->store_result(); // Store the result set
    $annotationsStmt->bind_result($annotationId, $annotationText, $xCoordinate, $yCoordinate);

    // Fetch the annotations into an array
    $annotations = array();
    while ($annotationsStmt->fetch()) {
        $annotations[] = array(
            'id' => $annotationId,
            'annotation_text' => $annotationText,
            'x_coordinate' => $xCoordinate,
            'y_coordinate' => $yCoordinate
        );
    }

    // Handle form submission to select annotations
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Retrieve the selected annotations from the form submission
        $selectedAnnotations = $_POST['selected_annotations'];

        // Check if the delete annotation button was clicked
        if (isset($_POST['delete_annotation'])) {
            // Delete the selected annotations from the database
            foreach ($selectedAnnotations as $annotationId) {
                $deleteStmt = $conn->prepare("DELETE FROM annotations WHERE id = ?");
                $deleteStmt->bind_param("i", $annotationId);
                $deleteStmt->execute();
            }
        }
    } else {
        // Set default selected annotations (if any)
        $selectedAnnotations = array();
    }

    // Close the annotations statement
    $annotationsStmt->close();

    // Display the photo as a panorama viewer using Photo Sphere Viewer
    echo '
    <html>
    <head>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@photo-sphere-viewer/core/index.min.css">
        <link rel="stylesheet" href="./panorama_viewer.css">
        <style>
            .annotation-marker {
                position: absolute;
                background-color: rgba(0, 0, 0, 0.8);
                color: white;
                padding: 4px;
                border-radius: 4px;
                font-size: 12px;
            }
        </style>
    </head>
    <body>
        <div class="panorama-wrapper">
            <div id="viewer" style="width: 80vw; height: 80vh;"></div>
        </div>

        <div class="annotations-wrapper">
            <!-- Annotation selection form -->
            <form method="POST" class="annotation-selection-form">
                <label for="selected_annotations">Select Annotations:</label><br>
                <ul style="text-align: left;">';

    // Display the available annotations as options in the selection form
    foreach ($annotations as $annotation) {
        echo '<li>
                <label for="annotation_' . $annotation['id'] . '">
                    <input type="checkbox" id="annotation_' . $annotation['id'] . '" name="selected_annotations[]" value="' . $annotation['id'] . '"';
                    if (in_array($annotation['id'], $selectedAnnotations)) {
                        echo ' checked';
                    }
        echo '>' . $annotation['annotation_text'] . '
                </label>
            </li>';
    }

    echo '
                </ul>
                <input class="submit_btn"  type="submit" name="submit"value="Submit Annotation(s)"></button>
                <input class="delete_btn"  type="submit" name="delete_annotation" value="Delete Annotation(s)"></button>
            </form>
        </div>

        <div id="annotation-form" class="annotation-form-container">
            <form id="add-annotation-form" method="POST" action="add_annotation.php">
                <label for="annotation-text">Annotation Text:</label>
                <input type="text" id="annotation-text" name="annotation-text">
                <label for="x-coordinate">X-coordinate:</label>
                <input type="text" id="x-coordinate" name="x-coordinate">
                <label for="y-coordinate">Y-coordinate:</label>
                <input type="text" id="y-coordinate" name="y-coordinate">
                <input class="add_btn" button type="submit" value="Add Annotation"></button>
            </form>
        </div>';

    // Display the selected annotations as markers on the photo
    foreach ($annotations as $annotation) {
        if (in_array($annotation['id'], $selectedAnnotations)) {
            $x = $annotation['x_coordinate'];
            $y = $annotation['y_coordinate'];

            // Adjust the coordinates to ensure they are within the bounds of the photo
            $x = max(0, min(1, $x)); // Clamp x coordinate between 0 and 1
            $y = max(0, min(1, $y)); // Clamp y coordinate between 0 and 1

            // Convert the adjusted coordinates to percentages for positioning on the photo
            $xPercent = $x * 100;
            $yPercent = $y * 100;

            // Display the annotation marker
            echo '<div class="annotation-marker" style="left: ' . $xPercent . '%; top: ' . $yPercent . '%;">' . $annotation['annotation_text'] . '</div>';
        }
    }

    echo '
        <script src="https://cdn.jsdelivr.net/npm/three/build/three.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/@photo-sphere-viewer/core/index.min.js"></script>
        <script>
            const viewer = new PhotoSphereViewer.Viewer({
                container: document.querySelector(\'#viewer\'),
                panorama: \'' . $fullPath . '\',
            });
        </script>

        <script>
            // Handle the form submission
            document.getElementById("add-annotation-form").addEventListener("submit", function (e) {
                e.preventDefault(); // Prevent the form from submitting

                // Get the form data
                var formData = new FormData(this);

                // Send the form data using AJAX
                fetch("add_annotation.php", {
                    method: "POST",
                    body: formData,
                })
                    .then(function (response) {
                        return response.json();
                    })
                    .then(function (data) {
                        if (data.status === "success") {
                            console.log("Annotation added successfully!");

                            // Clear the form fields
                            document.getElementById("annotation-text").value = "";
                            document.getElementById("x-coordinate").value = "";
                            document.getElementById("y-coordinate").value = "";

                            // Update the annotations container with the new annotation
                            var annotationsContainer = document.querySelector(".annotations-wrapper");

                            // Create a new annotation marker
                            var annotationMarker = document.createElement("div");
                            annotationMarker.className = "annotation-marker";
                            annotationMarker.innerText = formData.get("annotation-text"); // Use the entered annotation text
                            annotationMarker.style.left = formData.get("x-coordinate") + "%";
                            annotationMarker.style.top = formData.get("y-coordinate") + "%";

                            // Append the annotation marker to the annotations container
                            annotationsContainer.appendChild(annotationMarker);
                        } else {
                            console.log("Error: " + data.message);
                            // You can handle the error case here, such as displaying an error message to the user
                        }
                    })
                    .catch(function (error) {
                        console.log("Error: " + error);
                        // You can handle the error case here, such as displaying an error message to the user
                    });
            });

            // Handle the form submission for deleting an annotation
            document.querySelector(".annotation-selection-form").addEventListener("Delete Annotation", function (e) {
                e.preventDefault(); // Prevent the form from submitting

                // Retrieve the selected annotations from the form
                var selectedAnnotations = Array.from(this.elements["selected_annotations[]"].selectedOptions).map(option => option.value);

                // Send the selected annotations to the server for deletion
                fetch("delete_annotation.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                    },
                    body: JSON.stringify({ selectedAnnotations: selectedAnnotations }),
                })
                    .then(function (response) {
                        return response.json();
                    })
                    .then(function (data) {
                        if (data.status === "success") {
                            console.log("Annotation deleted successfully!");

                            // Remove the deleted annotation markers from the viewer
                            var annotationMarkers = document.querySelectorAll(".annotation-marker");
                            for (var i = 0; i < annotationMarkers.length; i++) {
                                var marker = annotationMarkers[i];
                                var annotationId = marker.dataset.annotationId;
                                if (selectedAnnotations.includes(annotationId)) {
                                    marker.remove();
                                }
                            }
                        } else {
                            console.log("Error: " + data.message);
                            // You can handle the error case here, such as displaying an error message to the user
                        }
                    })
                    .catch(function (error) {
                        console.log("Error: " + error);
                        // You can handle the error case here, such as displaying an error message to the user
                    });
            });
            
        </script>
    </body>
</html>';
} else {
    echo "Photo not found.";
}

$stmt->close();
$conn->close();
?>

