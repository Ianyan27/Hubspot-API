<?php
// Define the file name
$filename = "contacts_sample.csv";

// Initialize the data array with the header row
$data = [];
$data[] = ['Contact ID', 'Email', 'First Name', 'Last Name', 'delete_flag', 'gender'];

// Generate 50 dummy contacts
for ($i = 1; $i <= 50; $i++) {
    $contactId = 1000 + $i;
    $firstName = "Jedo{$i}";
    $lastName = "Last{$i}";
    $email = "contact{$i}@example.com";
    // Randomly assign 'Yes' or 'No' to delete_flag
    $deleteFlag = (rand(0, 1) === 1) ? 'Yes' : 'No';
    $gender = (rand(0, 1) === 1) ? 'Male' : 'Female';

    $data[] = [$contactId, $email, $firstName, $lastName, $deleteFlag, $gender];
}

// Open the file for writing
$fp = fopen($filename, 'w');

// Write each row to the CSV file
foreach ($data as $row) {
    fputcsv($fp, $row);
}

// Close the file handle
fclose($fp);

echo "CSV file created: " . $filename;
