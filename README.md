# InterSoccer Player Management Plugin

## Overview
The `player-management-plugin` enables InterSoccer Switzerland customers to manage their children (players) within their WooCommerce (WooComm) profile and assign them to soccer camps, courses, or birthday events during the booking process. This plugin is a core component of InterSoccer’s event booking system, built on WordPress (WP) and WooComm, designed to streamline parent and coach experiences.

**Author**: Jeremy Lee

## Features
- **Player Profiles**:
  - Customers can add, edit, and delete children’s profiles under "Manage Players" in their WooCommerce account.
  - Stores player details like name, age, and other relevant data for event assignments.
- **Dynamic Assignment**:
  - Integrates with `intersoccer-product-variations` to allow parents to assign players to events when adding products to the cart.
  - Displays dynamic forms for player selection during checkout.
- **Roster Generation**:
  - Provides coaches and organizers with real-time access to event rosters via integration with the `reports-rosters` plugin.
- **User Role Support**:
  - Supports custom roles (`coach`, `event organizer`) for accessing player data securely.

## Installation
1. **Clone the Repository**:
   ```bash
   git clone https://github.com/legit-ninja/player-management-plugin.git
   ```
2. **Install Dependencies**:
   Copy the plugin folder to `wp-content/plugins/`. Ensure WordPress, WooCommerce, and `intersoccer-product-variations` are installed.
3. **Activate Plugin**:
   In the WordPress admin panel, activate "InterSoccer Player Management".
4. **Configure User Roles**:
   Ensure custom roles (`coach`, `event organizer`, `customer`) are set up in WordPress.

## Usage
1. **Parent Workflow**:
   - Parents navigate to their WooCommerce account > "Manage Players" to add children.
   - During event booking, select a player from a dropdown to assign them to the camp, course, or birthday.
   - Player data is attached to the order and visible in confirmation emails.
2. **Coach/Organizer Workflow**:
   - Coaches access rosters via the `reports-rosters` plugin or admin dashboard to view assigned players for events.
3. **Admin Workflow**:
   - Shop managers can view and manage player assignments in WooCommerce orders.

## Development
- **Dependencies**: Requires WordPress, WooCommerce, and `intersoccer-product-variations`.
- **Testing**: Cypress tests are planned to validate player assignment and roster generation.
- **Code Structure**:
  - `includes/`: Logic for player profile management and assignment.
  - `assets/`: JS and CSS for frontend player forms.
  - `admin/`: Admin interfaces for managing player data.

## Contribution
1. Fork the repository.
2. Create a feature branch (`git checkout -b feature/YourFeature`).
3. Commit changes (`git commit -m 'Add YourFeature'`).
4. Push to the branch (`git push origin feature/YourFeature`).
5. Open a Pull Request.

Follow WordPress coding standards and include tests for new features.

## Issues
Report bugs or suggest features via the [GitHub Issues](https://github.com/legit-ninja/player-management-plugin/issues) page.

## License
GPLv2 or later, compatible with WordPress.
