# User Achievement Module

The **User Achievement** module provides a system for awarding badges to users for their engagement on the Joinup Portal.

## ✨ Features
- **Achievement Entities**: Manage individual achievement records.
- **Achievement Types**: Define specific badge types with human-readable titles, tooltip descriptions, and icons.
- **User Profile Integration**: Displays awarded badges on the user's profile page.
- **Automatic Awarding**: Hooks into system events (like joining a community or creating content) to automatically grant achievements.

## 🛠️ Components
- **Entities**:
  - `user_achievement`: The core entity that represents an achievement earned by a user.
  - `achievement_type`: Configuration entity that defines the badge (label, description, icon).
- **Field Formatter**: `user_achievements` - Renders the list of badges on the user entity as an item list.
- **Computed Field**: The `achievements` field is added to the User entity to dynamically fetch earned badges.

---

## 🖼️ Available Theme Icons (Ventuno)

The Joinup theme (**Ventuno**) provides a consolidated SVG sprite system based on **Bootstrap Icons (BI)**. These icons can be used across the portal in templates, JS, or by referencing their IDs.

The sprite is located at: `web/themes/ventuno/assets/icons/icons.svg`

### Icon IDs:
- `archive`
- `archive-fill`
- `arrow-left`
- `arrow-repeat`
- `arrow-right`
- `award`
- `bookmark`
- `bookmark-check-fill`
- `box-arrow-up-right`
- `calendar`
- `calendar-event`
- `card-list`
- `caret-down-fill`
- `chat`
- `chat-dots`
- `chat-left-text`
- `chat-square`
- `check-circle`
- `check-circle-fill`
- `chevron-bar-left`
- `chevron-bar-right`
- `chevron-down`
- `chevron-left`
- `chevron-right`
- `chevron-up`
- `clipboard2-check`
- `clock-history`
- `code-slash`
- `download`
- `envelope-fill`
- `exclamation-circle-fill`
- `exclamation-lg`
- `exclamation-triangle-fill`
- `eye`
- `facebook`
- `file-earmark`
- `file-earmark-check`
- `file-earmark-medical`
- `file-earmark-play`
- `file-earmark-plus`
- `file-earmark-post`
- `file-earmark-richtext`
- `file-earmark-text`
- `file-earmark-zip`
- `file-font`
- `file-x`
- `filter`
- `folder2-open`
- `gear-fill`
- `geo-alt`
- `github`
- `hand-thumbs-up`
- `hand-thumbs-up-fill`
- `info-circle`
- `info-circle-fill`
- `journal-text`
- `justify`
- `lightning-charge`
- `link-45deg`
- `linkedin`
- `mastodon`
- `newspaper`
- `patch-check`
- `patch-exclamation`
- `pencil-square`
- `people`
- `person-circle`
- `person-workspace`
- `pin-angle-fill`
- `play-btn`
- `plus`
- `question-lg`
- `reply`
- `rss`
- `search`
- `star`
- `three-dots`
- `trash`
- `twitter`
- `type`
- `vimeo`
- `x`
- `x-circle-fill`
- `x-lg`
- `x-square-fill`
- `youtube`

---

## 🔧 Usage

### In Twig Templates
Use the `<svg>` and `<use>` tags, referencing the theme's icon path:
```twig
<svg class="bi icon--s">
  <use xlink:href="{{ bcl_icon_path }}#award"></use>
</svg>
```

### In JavaScript
The path is available in `drupalSettings.bcl_icon_path`:
```javascript
const spritePath = drupalSettings.bcl_icon_path;
const iconHtml = `<svg class="bi icon--xs"><use xlink:href="${spritePath}#patch-check"></use></svg>`;
```

### Adding New Icons
To add a new icon to the sprite, copy its `<symbol>` definition from the individual `.svg` files in `web/themes/ventuno/assets/icons/` into `web/themes/ventuno/assets/icons/icons.svg`.
