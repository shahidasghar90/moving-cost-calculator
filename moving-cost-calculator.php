<?php
/*
Plugin Name: Moving Cost Calculator
Description: A simple plugin to calculate moving costs based on user inputs.
Version: 1.0
Author: github.com/shahidasghar90
*/

// Register a shortcode to display the calculator
add_shortcode('moving_calculator', 'moving_calculator_shortcode');

// Enqueue stylesheet for the calculator
function moving_calculator_enqueue_styles() {
    wp_enqueue_style('moving-calculator-style', plugins_url('style.css', __FILE__));
}
add_action('wp_enqueue_scripts', 'moving_calculator_enqueue_styles');

// Register the activation hook to create the custom table
register_activation_hook(__FILE__, 'create_moving_calculator_table');

function create_moving_calculator_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'moving_calculations';
    
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        total_space INT NOT NULL,
        floors INT NOT NULL,
        lift TINYINT(1) NOT NULL,
        extra_spaces TEXT,
        services TEXT,
        items TEXT,
        cost DECIMAL(10,2) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Handle AJAX request to calculate moving cost and save data
add_action('wp_ajax_calculate_moving_cost', 'calculate_moving_cost');
add_action('wp_ajax_nopriv_calculate_moving_cost', 'calculate_moving_cost');

function calculate_moving_cost() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'moving_calculations';

    // Basic calculation logic based on user inputs
    $total_space = isset($_POST['total_space']) ? intval($_POST['total_space']) : 0;
    $floors = isset($_POST['floors']) ? intval($_POST['floors']) : 0;
    $lift = isset($_POST['lift']) && $_POST['lift'] === 'yes' ? 1 : 0;
    $extra_spaces = isset($_POST['extra_spaces']) ? implode(',', $_POST['extra_spaces']) : '';
    $services = isset($_POST['services']) ? implode(',', $_POST['services']) : '';
    $items = isset($_POST['items']) ? json_encode($_POST['items']) : '{}';

    // Example cost calculation logic (you should customize this)
    $cost = ($total_space * 100) + ($floors * 50) + (count(explode(',', $extra_spaces)) * 100) + (count(explode(',', $services)) * 200) + (array_sum(json_decode($items, true)) * 50);
    if (!$lift) $cost += 500;

    // Save data to the database
    $wpdb->insert(
        $table_name,
        [
            'name' => sanitize_text_field($_POST['name']),
            'email' => sanitize_email($_POST['email']),
            'total_space' => $total_space,
            'floors' => $floors,
            'lift' => $lift,
            'extra_spaces' => $extra_spaces,
            'services' => $services,
            'items' => $items,
            'cost' => $cost
        ]
    );

    echo json_encode(['cost' => $cost]);
    wp_die();
}

// Display the calculator form via shortcode
function moving_calculator_shortcode() {
    ob_start();
    moving_calculator_form();
    return ob_get_clean();
}

function moving_calculator_form() {
    ?>
    <form id="moving-calculator-form">
        <h2>Move Details</h2>
        <label for="total_space">Total Space (m²):</label>
        <input type="number" id="total_space" name="total_space" required><br><br>

        <label for="floors">Number of Floors:</label>
        <input type="number" id="floors" name="floors" required><br><br>

        <label for="lift">Lift Available:</label>
        <select id="lift" name="lift" required>
            <option value="no">No</option>
            <option value="yes">Yes</option>
        </select><br><br>

        <label for="extra_spaces">Extra Spaces:</label>
        <input type="checkbox" id="garage" name="extra_spaces[]" value="garage"> Garage
        <input type="checkbox" id="basement" name="extra_spaces[]" value="basement"> Basement<br><br>

        <label for="services">Services Required:</label>
        <input type="checkbox" id="packing" name="services[]" value="packing"> Need Packing Material
        <input type="checkbox" id="disposal" name="services[]" value="disposal"> Disposal
        <input type="checkbox" id="unpacking" name="services[]" value="unpacking"> Unpack Cartons<br><br>

        <h2>Move Items</h2>
        <label for="sofa">Sofa:</label>
        <input type="number" id="sofa" name="items[sofa]" min="0" value="0"><br>
        <label for="chair">Chair:</label>
        <input type="number" id="chair" name="items[chair]" min="0" value="0"><br>
        <label for="table">Table:</label>
        <input type="number" id="table" name="items[table]" min="0" value="0"><br>
        <label for="lcd">LCD:</label>
        <input type="number" id="lcd" name="items[lcd]" min="0" value="0"><br>

        <h2>Contact Information</h2>
        <label for="name">Name:</label>
        <input type="text" id="name" name="name" required><br><br>

        <label for="email">Email:</label>
        <input type="email" id="email" name="email" required><br><br>

        <input type="submit" value="Calculate">
    </form>

    <div id="moving-cost-result"></div>

    <script>
    document.getElementById('moving-calculator-form').addEventListener('submit', function(event) {
        event.preventDefault();
        let form = event.target;
        let data = new FormData(form);
        fetch('<?php echo admin_url('admin-ajax.php'); ?>?action=calculate_moving_cost', {
            method: 'POST',
            body: data
        })
        .then(response => response.json())
        .then(result => {
            document.getElementById('moving-cost-result').innerHTML = 'Estimated Cost: €' + result.cost;
        })
        .catch(error => console.error('Error:', error));
    });
    </script>
    <?php
}

// Add admin menu page
add_action('admin_menu', 'moving_calculator_admin_menu');

function moving_calculator_admin_menu() {
    add_menu_page(
        'Moving Calculator',
        'Moving Calculator',
        'manage_options',
        'moving_calculator',
        'moving_calculator_admin_page',
        'dashicons-calculator'
    );
}

function moving_calculator_admin_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'moving_calculations';

    $results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");

    ?>
    <div class="wrap">
        <h1>Moving Calculations</h1>
        <table class="widefat fixed">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Total Space</th>
                    <th>Floors</th>
                    <th>Lift</th>
                    <th>Extra Spaces</th>
                    <th>Services</th>
                    <th>Items</th>
                    <th>Cost</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results as $row) : ?>
                <tr>
                    <td><?php echo esc_html($row->id); ?></td>
                    <td><?php echo esc_html($row->name); ?></td>
                    <td><?php echo esc_html($row->email); ?></td>
                    <td><?php echo esc_html($row->total_space); ?></td>
                    <td><?php echo esc_html($row->floors); ?></td>
                    <td><?php echo esc_html($row->lift ? 'Yes' : 'No'); ?></td>
                    <td><?php echo esc_html($row->extra_spaces); ?></td>
                    <td><?php echo esc_html($row->services); ?></td>
                    <td><?php echo esc_html($row->items); ?></td>
                    <td><?php echo esc_html($row->cost); ?></td>
                    <td><?php echo esc_html($row->created_at); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}
?>
