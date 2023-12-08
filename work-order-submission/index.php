<?php

include "../lib/site.php";
require_once "../lib/functions.php";
include "states_countries.php";
require_once("$serverRoot/lib/mailer.php");
require_once "$serverRoot/lib/FormKey.php";

use GuzzleHttp\Client;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

$formkey = new FormKey();
$sitekey = get_config_value("recaptcha", "sitekey");

// Sample Name = alphanumeric and underscores only.
// Volume  = numbers only
// Concentration = numbers only
// External Sys ID = any chars
// Tube Label = any chars

$js = array('work-order-submission', 'char',
    'char_limit',
    "https://www.google.com/recaptcha/api.js"
);

$css = array('form', 'work-order', 'dialogs');
$title = "Work Order Submission";



// Form POST
$first_name = isset($_POST['first-name']) ? $_POST['first-name'] : '';
$last_name = isset($_POST['last-name']) ? $_POST['last-name'] : '';
$email = isset($_POST['email']) ? $_POST['email'] : '';
$institution = isset($_POST['institution']) ? $_POST['institution'] : '';
$phone_number = isset($_POST['phone-number']) ? $_POST['phone-number'] : '';
$purchase_order_number = isset($_POST['purchase-order-number'])
    ? $_POST['purchase-order-number']
    : '';
$grant_number = isset($_POST['grant-number']) ? $_POST['grant-number'] : '';

$billing_street = isset($_POST['billing-street']) ? $_POST['billing-street'] : '';
$billing_city = isset($_POST['billing-city']) ? $_POST['billing-city'] : '';
$billing_zip_code = isset($_POST['billing-zip-code']) ? $_POST['billing-zip-code'] : '';
$billing_country = isset($_POST['billing-country']) ? $_POST['billing-country'] : '';
$data_services = isset($_POST['data-services'])
    ? $_POST['data-services']
    : '';
$data_delivery_options = isset($_POST['data-delivery-options'])
    ? $_POST['data-delivery-options']
    : '';
$comments = isset($_POST['comments']) ? $_POST['comments'] : '';

$data_services_other = isset($_POST['data-services-other'])
    ? $_POST['data-services-other']
    : '';

$billing_first_name = isset($_POST['billing-first-name'])
    ? $_POST['billing-first-name']
    : '';
$billing_last_name = isset($_POST['billing-last-name'])
    ? $_POST['billing-last-name']
    : '';
$billing_email = isset($_POST['billing-email']) ? $_POST['billing-email'] : '';


$errors = array();
$metadata = NULL;
$errors_listed = array();


$work_order = isset($_POST['work-order-types'])
    ? $_POST['work-order-types']
    : '';

// File Upload Array
$file = isset($_FILES['file'])
    ? $_FILES['file']
    : '';
$file_name = isset($_FILES['file']['name'])
    ? $_FILES['file']['name']
    : '';
$file_tmp = isset($_FILES['file']['tmp_name'])
    ? $_FILES['file']['tmp_name']
    : '';
$file_size = isset($_FILES['file']['size'])
    ? $_FILES['file']['size']
    : '';
$file_error = isset($_FILES['file']['error'])
    ? $_FILES['file']['error']
    : '';

// Form Submit to False
$submitted = false;

// Data Services Array for Form Select/Options
$data_services = array(
    'data_service' => 'data_service',

);

$receivable_options = array(
    'Dropped off' => ' Dropped off',
    'Shipped' => ' Shipped',
    'Samples already' =>
        ' Samples already'
);

$data_delivery_options = array(
    'As download' => 'As Download',
    'aws-s3-bucket' => 'Customer-supplied Amazon S3 bucket'
);

// Here we are creating more complex structure to add in a href
// into the options for downloads.the download is handle by js.

$work_order_types = array(
    'work-order' => 'Work Order',
    
);

$default_work_order_option = [
    'value' => '',
    'label' => '-- Confirm Work Order Type --'
];



$work_order_types_selected = isset($_REQUEST['work-order-types'])
    ? $_REQUEST['work-order-types']
    : '';

// Data Analysis Request what was chosen
$data_analysis_services_selected = isset($_REQUEST['data-services'])
    ? $_REQUEST['data-services']
    : '';

// Receivable Options what was chosen
$receivable_options_selected = isset($_REQUEST['receivable-options'])
    ? $_REQUEST['receivable-options']
    : '';

// Data Delivery Request what was chosen
$data_delivery_options_selected = isset($_REQUEST['data-delivery-options'])
    ? $_REQUEST['data-delivery-options']
    : '';

// Request State and Country
$state_selected = isset($_REQUEST['billing-state']) ? $_REQUEST['billing-state'] : '';
$country_selected = isset($_REQUEST['billing-country']) ? $_REQUEST['billing-country'] : '';


// Allow only alphanumeric and underscores for excel
function validate_alpha_and_underscores($str) {
    $allowed = preg_match_all('/^[A-Za-z0-9]*(?:_[A-Za-z0-9]+)*$/', $str);

    // Return 0 is fail 1 is matched.
    return $allowed;
}


// This is mainly to allow spaces, dashes, and underscores
// spaces cannot be at the beginning or end of the string
function validate_alpha_dashes_underscores($str) {
    $valid_exp = preg_match('/^[A-Za-z0-9]+(?:[ _-]*[A-Za-z0-9]+)*$/', $str);

    return $valid_exp;
}



// Get the required data from the json file
function get_required_data() {
    global $work_order;
    global $serverRoot;

    // serverRoot

    $required_data_path = "$serverRoot/";

    $required_data = file_get_contents($required_data_path);
    $encoded_data = array();
    $encoded_data = json_decode($required_data, TRUE);

    $req_json_fields = array();


    if (array_key_exists($work_order, $encoded_data)) {
        $req_json_fields = $encoded_data[$work_order]['required_fields'];
    } else {
        error_log('Could not find the work order');
    }

    // Combine the array of arrays into one array
    $combine_req_fields = array_merge(...array_values($req_json_fields));

    return $combine_req_fields;
}


// Validate Form Data
function error_check() {
    $errors = array();
    $recaptcha_success = get_recaptcha_success();

    if (empty($_POST['first-name'])) {
        array_push($errors, 'Please fill out the first name.');
    } elseif (strlen($_POST['first-name']) > 50) {
        $_POST['first-name'] = mb_strimwidth($_POST['first-name'], 0, 50, "");
        array_push($errors, 'The first name cannot be more than 50 characters.');
    }

    if (empty($_POST['last-name'])) {
        array_push($errors, 'Please fill out the last name.');
    } elseif (strlen($_POST['last-name']) > 50) {
        $_POST['last-name'] = mb_strimwidth($_POST['last-name'], 0, 50, "");
        array_push($errors, 'The last name cannot be more than 50 characters.');
    }

    if (empty($_POST['email'])) {
        array_push($errors, "The email address cannot be left blank.");
    } elseif (!valid_email($_POST['email'])) {
        array_push($errors, "Invalid email address.");
    } elseif (strlen($_POST['email']) > 128) {
        $_POST['email'] = mb_strimwidth($_POST['email'], 0, 128, "");
        array_push($errors, 'The email address cannot be more than 128 characters.');
    }

    if (empty($_POST['phone-number'])) {
        array_push($errors, 'Please fill out phone number.');
    } elseif (strlen($_POST['phone-number']) > 128) {
        $_POST['phone-number'] = mb_strimwidth($_POST['phone-number'], 0, 128, "");
        array_push($errors, 'The phone number cannot be more than 128 characters.');
    }


    // Purchase Section

    if (!empty($_POST['purchase-order-number']) &&
        !validate_alpha_dashes_underscores($_POST['purchase-order-number'])) {
        array_push($errors, "The purchase order number can only contain letters, numbers, and underscores.");
    } elseif (strlen($_POST['purchase-order-number']) > 50) {
        $_POST['purchase-order-number'] = mb_strimwidth($_POST['purchase-order-number'], 0, 50, "");
        array_push($errors, 'The purchase order number cannot be more than 8 characters.');
    }



    // Billing Section

    if (!empty($_POST['billing-first-name']) && strlen($_POST['billing-first-name']) > 50) {
        $_POST['billing-first-name'] = mb_strimwidth($_POST['billing-first-name'], 0, 50, "");
        array_push($errors, 'The billing first name cannot be more than 50 characters.');
    }

    if (!empty($_POST['billing-last-name']) && strlen($_POST['billing-last-name']) > 50) {
        $_POST['billing-last-name'] = mb_strimwidth($_POST['billing-last-name'], 0, 50, "");
        array_push($errors, 'The billing last name cannot be more than 50 characters.');
    }

    if (!empty($_POST['billing-email']) && strlen($_POST['billing-email']) > 128) {
        $_POST['billing-email'] = mb_strimwidth($_POST['billing-email'], 0, 128, "");
        array_push($errors, 'The billing email cannot be more than 128 characters.');
    } elseif (!empty($_POST['billing-email']) && !valid_email($_POST['billing-email'])) {
        array_push($errors, "Invalid billing email address.");
    }

    if (!empty($_POST['billing-street']) && strlen($_POST['billing-street']) > 128) {
        $_POST['billing-street'] = mb_strimwidth($_POST['billing-street'], 0, 128, "");
        array_push($errors, 'The billing street cannot be more than 128 characters.');
    }

    if (!empty($_POST['billing-city']) && strlen($_POST['billing-city']) > 128) {
        $_POST['billing-city'] = mb_strimwidth($_POST['billing-city'], 0, 128, "");
        array_push($errors, 'The billing city cannot be more than 128 characters.');
    }

    if (!empty($_POST['billing-zip-code']) && strlen($_POST['billing-zip-code']) > 32) {
        $_POST['billing-zip-code'] = mb_strimwidth($_POST['billing-zip-code'], 0, 32, "");
        array_push($errors, 'The billing zip code cannot be more than 32 characters.');
    }


    if ($_POST['work-order-types'] === 'none' || empty($_POST['work-order-types'])) {
        array_push($errors, 'Please confirm the work order you are uploading.');
    }


    if (!empty($_POST['data-services-other']) &&
        strlen($_POST['data-services-other']) > 50) {
        $_POST['data-services-other'] = mb_strimwidth($_POST['data-services-other'], 0, 50, "");
        array_push($errors, 'The data services other cannot be more than 50 characters.');
    }

    // Files

    if (empty($_FILES['file']) ||
        $_FILES['file']['error'] == UPLOAD_ERR_NO_FILE) {
        array_push($errors, "You did not attach a file.");
    } else {
        $accepted_mime_type = array(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel'
        );
        $mime_type = mime_content_type($_FILES['file']['tmp_name']);

        // Check for mime type
        if (! in_array($mime_type, $accepted_mime_type)) {
            array_push($errors, 'Please use the Excel file that was provided.');
            error_log('This was not the right mime type');
        }

        $allowed_exts = array('xlsx', 'xls');
        $ext = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);

        // Check for file ext
        if (! in_array($ext, $allowed_exts)) {
            array_push($errors, "This file extension is not allowed");
        }

        // Check file if over 3mbg then error it out
        if ($_FILES['file']['size'] > 2000000) {
            array_push($errors, "Your file exceeds 2MB. Please reduce.");
        }
    }

    if (! $recaptcha_success) {
        array_push($errors, "Recaptcha not successfully completed. Please try again.");
    }

    return $errors;
}



function extract_data_rows() {
    $input_excel_file_type = 'Xlsx';
    $sheet_name = 'Sample Information';
    $input_excel_file = $_FILES['file']['tmp_name'];
    $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($input_excel_file_type);
    $reader->setLoadSheetsOnly($sheet_name);
    $reader->setReadEmptyCells(false);
    $reader->setReadDataOnly(true);

    $spreadsheet = $reader->load($input_excel_file);


    // First let's extract one cell value to tell us which file is being uploaded.

    $work_order_type = $spreadsheet->getActiveSheet()->getCell('A7')->getValue();


    // Pull the header information from the spreadsheet,
    // which is only row 8
    $header_data = $spreadsheet->getActiveSheet()->rangeToArray(
        'B8:AA8', NULL, false, false, true);

    global $headers;
    $headers = $header_data[8];


    $chunk_size = 1000;

    // The row that the real data actually begins on. The contents of earlier
    // rows are just headers and other information.;
    $start_row = 9;

    $pass = 1;

    $data_detected = true;

    $rows_with_data = array();

    while ($data_detected) {
        $start_cell = "B" .
            strval(($start_row + ($chunk_size * ($pass - 1))));
        $end_cell = "AA" .
            strval(($start_row - 1) + ($chunk_size * $pass));
        // pass 1:  B9:AA1008
        // pass 2:  B1009:AA2008
        // pass 3:  B2009:AA3008
        // ...
        $cell_range = $start_cell . ":" . $end_cell;


        $sheet_data = $spreadsheet->getActiveSheet()->rangeToArray(
            $cell_range, null, true, true, true);

        $chunk_has_data = False;

        foreach ($sheet_data as $row_number => $row) {
            // Filter out rows that are entirely blank. If even a single
            // element in a row has data, then the row will be included...
            if (array_filter($row)) {
                // Set a flag that we have detected data in this pass.
                $chunk_has_data = true;

                foreach ($headers as $column_letter => $column_name) {
                    $row[$column_name] = $row[$column_letter];

                    unset($row[$column_letter]);
                }

                // Save the row number so that we can refer to it later.
                $row['row_number'] = $row_number;
                array_push($rows_with_data, $row);
            } else {
                // Row was empty
                $chunk_has_data = False;
            }
        }

        // No rows with data detected on this pass, so stop
        //the while loop.
        $data_detected = $chunk_has_data;

        // Increment the pass counter
        $pass++;
    }

    return array($rows_with_data, $work_order_type);
}

// Create Function to get Temp directory

function get_temporary_dir() {
    // Get the system's temporary directory path
    $temp_dir = sys_get_temp_dir();

    error_log("Temporary directory is: $temp_dir");

    // Create a unique subdirectory name, for example, using a timestamp
    $unique_dir_name = 'temp_mdg_work_order_'. uniqid();

    error_log("Unique directory name is: $unique_dir_name");

    // Combine the temporary directory path and the unique subdirectory name
    $full_temp_dir_path = $temp_dir . DIRECTORY_SEPARATOR . $unique_dir_name;

    // Create the temporary directory
    if (mkdir($full_temp_dir_path, 0700)) {
        return $full_temp_dir_path;
    } else {
        throw new Exception("Failed to create temporary directory");
    }
}


function send_redis_message($time_stamp, $saved_file_name) {
    Predis\Autoloader::register();

    $redis_server = get_config_value('redis', 'server');
    $redis_port = get_config_value('redis', 'port');
    $redis_key = "";

    try {
        $redis = new Predis\Client(
            array(
                "scheme" => "tcp",
                "host"   => $redis_server,
                "port"   => $redis_port
            )
        );

        error_log("Successfully connected to Redis: $redis_server, $redis_port");

        $redis_message = array(
            'ts' => $time_stamp,
            'file' => $saved_file_name
        );

        $redis->rpush($redis_key, json_encode($redis_message));

        return $redis_message;
    } catch (Exception $e) {
        error_log("Couldn't send message to Redis: " . $e->getMessage());
        return NULL;
    }
}


function validate_received_headers($headers) {
    $errors = array();
    $valid = true;

    $req_fields = get_required_data();
    $missing_fields = array();

    foreach ($req_fields as $req_field) {
        if (!in_array($req_field, $headers)) {
            $valid = false;
            $missing_fields[] = $req_field;
        }
    }

    return [$valid, $errors, $missing_fields];
}


function validate_file_row($row) {
    $errors = array();
    $valid = true;

    $req_fields = get_required_data();


    // loop row against req fields if they are there
    // then see if the row has a value
    foreach ($row as $key => $value) {
        if (in_array($key, $req_fields)) {
            if (empty($value)) {
                $valid = false;
                $error_message = "The excel required field " . $key . " is empty. Please be sure fill out all required fields.";
                array_push($errors, $error_message);
            }
        }

        if ($key === 'Sample Name*') {
            if (!validate_alpha_and_underscores($value)) {
                $valid = false;
                $error_message = "The excel required field " . $key . " can only contain letters, numbers, and underscores.";
                array_push($errors, $error_message);
            }
        }
        if ($key === 'Volume (ul)*' || $key === 'Concentration (ng/ul)*' || $key === 'External Concentration (ng/ul)*') {
            if (!preg_match('/^[0-9]+(\.[0-9]+)?$/', $value)) {
                $valid = false;
                $error_message = "The excel required field " . $key . " can only contain numbers.";
                array_push($errors, $error_message);
            }
        }
    }


    return [$valid, $errors];

}



function get_recaptcha_success() {
    $secretkey = get_config_value("recaptcha", "secretkey");

    // Set captcha to false
    $recaptcha_success = false;


    try {

        // Get the response from the recaptcha
        $recaptcha_response = $_POST["g-recaptcha-response"];

        // Create a new Guzzle client to
        // communicate with the recaptcha server
        $client = new Client();

        // Create the payload to send to the recaptcha server
        $post_data = array(
            "secret"   => $secretkey,
            "response" => $recaptcha_response
        );

        // The url of the recaptcha server
        $url = 'https://www.google.com/recaptcha/api/siteverify';

        // Send the post request to the recaptcha server
        $response = $client->post($url, [ 'form_params' => $post_data,
            'timeout' => 5.0
        ]);

        // Get the response from the recaptcha server and decode
        $json = json_decode($response->getBody()->getContents(), true);

        // Get the success value from the json response
        $recaptcha_success = $json['success'];
    } catch (Exception $e) {
        // If there is an error, log it
        error_log($e);
    }

    // This will return true or false
    return $recaptcha_success;
}


function make_tarball($json_data, $uploaded_file) {
    error_log("In make_tarball.");

    global $upload_dir;
    global $compressed_gz;

    // Get the current time and date
    $file_time = get_timestamp();

    // Create the json file name
    $json_filename = "work-order-submission-" . $file_time . ".json";

    // Take the excel file attached an send it to the directory
    $excel_filename = "work-order-submission-" . $file_time . ".xlsx";

    $temp_dir = get_temporary_dir();

    // Move the json and excel to a temp directory
    $json_full_path = $temp_dir . '/' . $json_filename;
    $excel_full_path = $temp_dir . '/' . $excel_filename;

    file_put_contents($json_full_path, $json_data);
    error_log("JSON file saved to: " . $json_full_path);

    move_uploaded_file($uploaded_file, $excel_full_path);
    error_log("Uploaded Excel file moved to: " . $excel_full_path);

    #$tar_file = $temp_dir . "/work-order-submission-" . $file_time. '.tar';
    $tar_file = "work-order-submission-" . $file_time. '.tar';

    // Lets try to create a tar with shell_exec
    chdir($temp_dir);
    $command = "/bin/tar -cf ./$tar_file -C $temp_dir $json_filename $excel_filename";
    error_log("Tar command: $command");

    $output = shell_exec($command);

    if (file_exists($tar_file)) {
        error_log("Tar file $tar_file created successfully.");
    } else {
        throw new Exception("Failed to create the tar file.");
    }

    $compressed_gz = basename($tar_file) . '.gz';
    error_log("Compressed file name: " . $compressed_gz);

    // Read the contents of the tar file
    $tar_contents = file_get_contents($tar_file);
    error_log("Tar file contents: " . $tar_contents);

    // Compress the tar file
    $compressed = gzencode($tar_contents, 9, FORCE_GZIP);
    file_put_contents($compressed_gz, $compressed);

    $compressed_gz_destination = $upload_dir . '/' . $compressed_gz;

    // I move the file without naming it
    if (rename($compressed_gz, $compressed_gz_destination)) {
        error_log("Compressed .gz file moved to: " . $compressed_gz_destination);
    } else {
        error_log("Failed to move compressed .gz file to the upload directory.");
    }

    error_log("Returning $tar_file");
    return $tar_file;
}


if (isset($_POST['submit'])) {
    // Start Variables
    $submitted = true;
    $errors = error_check();
    $file_date = date("Y-m-d-H:i:s");
    $data = array();
    

    if ($formkey->validate()) {
        
        error_log("Form key validated.");

        if (count($errors) == 0) {
            // Create a variable to hold
            $extracted_rows = extract_data_rows();

            // Check the headers before validating row.
            list($valid, $errors, $missing_fields) = validate_received_headers($headers);

            if (!$valid) {

                $missing_fields_string = implode(", ", $missing_fields);
                $error_message = "The following required fields are missing: " . $missing_fields_string.
                    " Please check to see if excel file  work order type selection match.";
                array_push($errors, $error_message);

            }

            // Extract all rows that have any data in them.
            // Strip off any rows that are empty. Target first in
            // the return array
            $rows_with_data = $extracted_rows[0];

            // Here is the work order type
            // it is returned from the extracted rows function
            // Second item in the array
            $row_work_order_type = $extracted_rows[1];

            // The next step is to go through each row,
            // and validate the data inside.
            $errors_detected = False;
            $errors_listed = array();


            if (count($rows_with_data) > 0) {
                foreach ($rows_with_data as $row) {
                    $valid_response = validate_file_row($row);

                    $valid = $valid_response[0];
                    $row_errors = $valid_response[1];


                    if ($valid) {
                        // $row was valid.
                    } else {
                        $errors_detected = True;
                        // not valid
                        foreach ($row_errors as $row_error) {
                            $numbered_row_error = "Row ". $row['row_number'] . ": " .$row_error;
                            array_push($errors_listed, $numbered_row_error);
                        }
                    }
                }
            } else {
                $errors_detected = True;
                array_push($errors_listed, "The file has no data.");
            }

            if ($errors_detected) {
                if (count($errors_listed) > 5) {
                    $first_five = array_slice($errors_listed, 0, 5);

                    // Push onto array and fake heading with CSS
                    $too_many_errors = "There are too many errors. " .
                        "Here are the first 5:";
                    $errors_listed = array($too_many_errors);

                    $errors_listed = array_merge($errors_listed, $first_five);
                }
            }


            if (!$errors_detected) {

                error_log("The file was valid.");

                $data = [
                    "timestamp" => $file_date,
                    "form_data" => [
                        'first_name' => $first_name,
                        'last_name' => $last_name,
                        'email' => $email,
                        'institution' => $institution,
                        'phone_number' => $phone_number,
                        'purchase_order_number' => $purchase_order_number,
                        'grant_number' => $grant_number,
                        'dept_number' => $dept_number,
                        'quote_number' => $quote_number,
                        'date_dropped_or_shipped' => $date_dropped_or_shipped,
                        'billing_first_name' => $billing_first_name,
                        'billing_last_name' => $billing_last_name,
                        'billing_email' => $billing_email,
                        'billing_street' => $billing_street,
                        'billing_city' => $billing_city,
                        'billing_state' => $state_selected,
                        'billing_zip_code' => $billing_zip_code,
                        'billing_country' => $billing_country,
                        'data_services_selected' => $data_services_selected,
                        'data_services_other' => $data_services_other,
                        'data_delivery_options_selected' => $data_delivery_options_selected,
                        'receivable_options_selected' => $receivable_options_selected,
                        'comments' => str_replace('$', '\$', $comments),
                        'work_order' => $work_order
                    ]
                ];

                // Encode JSON
                $json_data = json_encode($data, JSON_PRETTY_PRINT);

                $upload_dir = $serverRoot . '/work-order-submission/work-orders';
                error_log("Upload directory is: $upload_dir");


                if (is_dir($upload_dir) && is_writable($upload_dir)) {

                    try {
                        $tarball = make_tarball($json_data, $file_tmp);

                        // Call the send_redis_message function to send the message to Redis
                        $redis_message = send_redis_message($file_date, $compressed_gz);


                        error_log("Redis message sent: " . json_encode($redis_message));
                    } catch (Exception $e) {
                        error_log("Error: " . $e->getMessage());
                    }

                } else {
                    array_push($errors, "Something went wrong, please try again later.");
                    error_log("Upload directory is not writable.");
                }
            }
        }
    } else {
        // In the event the auth key fails we can show an error to the user.
        array_push($errors, "Something went wrong. Your session has ended. Please refresh and try again.");
    }
}



$smarty = get_smarty();

$smarty->assign('css', $css);
$smarty->assign('js', $js);
$smarty->assign('formkey', $formkey);
$smarty->assign('sitekey', $sitekey);
$smarty->assign('submitted', $submitted);
$smarty->assign('errors_listed', $errors_listed);
$smarty->assign('errors', $errors);
$smarty->assign('title', $title);
$smarty->assign('data_services', $data_services);
$smarty->assign('data_services_selected', $data_services_selected);
$smarty->assign('data_services_other', $data_services_other);
$smarty->assign('data_delivery_options', $data_delivery_options);
$smarty->assign('data_delivery_options_selected', $data_delivery_options_selected);
$smarty->assign('states', $states);
$smarty->assign('state_selected', $state_selected);
$smarty->assign('countries', $countries);
$smarty->assign('country_selected', $country_selected);
$smarty->assign('first_name', $first_name);
$smarty->assign('last_name', $last_name);
$smarty->assign('email', $email);
$smarty->assign('institution', $institution);
$smarty->assign('phone_number', $phone_number);
$smarty->assign('receivable_options', $receivable_options);
$smarty->assign('receivable_options_selected', $receivable_options_selected);
$smarty->assign('billing_first_name', $billing_first_name);
$smarty->assign('billing_last_name', $billing_last_name);
$smarty->assign('billing_email', $billing_email);
$smarty->assign('billing_street', $billing_street);
$smarty->assign('billing_city', $billing_city);
$smarty->assign('billing_zip_code', $billing_zip_code);
$smarty->assign('purchase_order_number', $purchase_order_number);
$smarty->assign('grant_number', $grant_number);
$smarty->assign('dept_number', $dept_number);
$smarty->assign('quote_number', $quote_number);
$smarty->assign('date_dropped_or_shipped', $date_dropped_or_shipped);
$smarty->assign('comments', $comments);
$smarty->assign('work_order_types', $work_order_types);
$smarty->assign('work_order_types_selected', $work_order_types_selected);
$smarty->assign('default_work_order_option', $default_work_order_option);


$smarty->assign('file', $file);

$smarty->display("work-order-submission/index.tpl");

?>
