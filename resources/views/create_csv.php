<?php
// Define the file name
$filename = "contacts_sample.csv";

// Define your dummy data rows
$data = [
    ['Contact ID', 'Email', 'First Name', 'Last Name', 'delete_flag'],
    [1001, 'john.doe@example.com', 'John', 'Doe', 'Yes'],
    [1002, 'jane.smith@example.com', 'Jane', 'Smith', 'No'],
    [1003, 'bob.jones@example.com', 'Bob', 'Jones', 'Yes']
];

// Open the file for writing
$fp = fopen($filename, 'w');

// Loop through each row and write it to the CSV file
foreach ($data as $row) {
    fputcsv($fp, $row);
}

// Close the file handle
fclose($fp);

echo "CSV file created: " . $filename;
