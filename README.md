# Photo Contest

A WordPress plugin that allows users to vote for photos in a contest.

## Description

This plugin creates a photo contest system where:
- Photos are automatically imported from the WordPress Photo Directory based on a hashtag
- Users can vote for photos using a 1-5 rating system
- Results are displayed in a ranking table
- All photos are displayed in an archive page

## Installation

1. Upload the `photo-contest` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings > Photo Contest and configure the contest hashtag

## Required Pages

For the plugin to work properly, you need to create the following pages:

1. **Voting Page**
   - Create a page with the shortcode: `[vote_photos]`
   - This page will display the voting interface where users can vote for photos
   - Users can also disqualify photos they consider inappropriate

2. **Results Page**
   - Create a page with the shortcode: `[vote_results]`
   - This page will display the top 10 photos ranked by average score

3. **Authors Report Page**
   - Create a page with the shortcode: `[authors_report]`
   - This page will display all authors grouped by their submitted photos
   - Shows author name, photo count, and thumbnails of all their photos

4. **Photos Archive**
   - Add this URL to your menu: `[your-site-url]/photos/`
   - This page will display all photos participating in the contest

## Voting System

- Users must be logged in to vote
- Each user can vote for each photo only once
- Users can disqualify photos they consider inappropriate
- Disqualified photos are excluded from voting
- Votes are converted to points:
  - 1 star = 0 points
  - 2 stars = 3 points
  - 3 stars = 5 points
  - 4 stars = 7 points
  - 5 stars = 10 points
- The average score is calculated from all votes

## Automatic Updates

The plugin automatically:
- Imports photos from the WordPress Photo Directory
- Updates the photos list periodically
- Calculates and updates the average scores
- Manages photo tags and disqualifications

## Requirements

- WordPress 5.7 or higher
- PHP 8.0 or higher
- A valid hashtag in the WordPress Photo Directory

## Frequently Asked Questions

### How do I change the contest hashtag?
Go to Settings > Photo Contest and enter the new hashtag (without the # symbol).

### Can users vote multiple times for the same photo?
No, each user can vote for each photo only once.

### How often are the photos updated?
Photos are updated automatically when the cron job runs. You can also update them manually from the settings page.

### Can users disqualify photos?
Yes, users can disqualify photos they consider inappropriate. Disqualified photos are excluded from the voting process.

### How do I view all authors and their photos?
Create a page with the shortcode `[authors_report]` to see all authors grouped by their submitted photos.

## Changelog

### 1.1.0
- Added photo disqualification feature
- Added authors report shortcode `[authors_report]`
- Enhanced voting interface with disqualify button
- Improved photo management with tags

### 1.0.0
- Initial release
- Basic voting functionality
- Automatic photo import
- Results table
- Settings page

## License

This plugin is licensed under the GPL v2 or later.

## Credits

Created by [Your Name] 