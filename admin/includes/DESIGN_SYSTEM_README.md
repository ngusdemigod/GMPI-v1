# Admin Dashboard Design System

## Overview

This design system provides centralized typography and styling tokens for the entire admin dashboard. All admin pages should use these design tokens to ensure consistency across the application.

## How to Use

### 1. Include the Design System CSS

Add this line to the `<head>` section of any admin page:

```html
<link href="includes/design-system.css" rel="stylesheet">
```

### 2. Change Typography in One Place

To modify typography for the entire dashboard, edit the CSS Custom Properties in `admin/includes/design-system.css`:

```css
:root {
  /* Change heading sizes */
  --type-h1-size: 28px;      /* Change this to update all h1 elements */
  --type-h2-size: 24px;      /* Change this to update all h2 elements */
  --type-h3-size: 20px;      /* Change this to update all h3 elements */
  
  /* Change body text size */
  --type-body: 14px;         /* Change this to update all body text */
  --type-body-sm: 13px;      /* Change this to update small body text */
  
  /* Change font families */
  --font-heading: 'Bricolage Grotesque', sans-serif;
  --font-body: 'Inter', sans-serif;
  
  /* Change colors */
  --text-primary: #0F1B2D;   /* Change to update all primary text */
  --text-secondary: rgba(15, 27, 45, 0.55);
  
  /* Change font weights */
  --font-weight-regular: 400;
  --font-weight-medium: 500;
  --font-weight-semibold: 600;
  --font-weight-bold: 700;
}
```

## Available Typography Classes

### Heading Classes
- `.text-h1` - Main page headings (28px, semibold)
- `.text-h2` - Section headings (24px, semibold)
- `.text-h3` - Subsection headings (20px, semibold)
- `.text-h4` - Card titles (17px, semibold)
- `.text-h5` - Small headings (15px, medium)
- `.text-h6` - Tiny headings (13px, medium)

### Body Text Classes
- `.text-body` - Default body text (14px)
- `.text-body-lg` - Large body text (16px)
- `.text-body-sm` - Small body text (13px)
- `.text-body-xs` - Extra small text (12px)
- `.text-body-xs-small` - Tiny text (11px)

### Utility Classes

#### Font Weight
- `.font-thin` (200)
- `.font-light` (300)
- `.font-regular`, `.font-normal` (400)
- `.font-medium` (500)
- `.font-semibold` (600)
- `.font-bold` (700)
- `.font-extrabold` (800)

#### Font Family
- `.font-heading` - Uses heading font
- `.font-body` - Uses body font
- `.font-mono` - Uses monospace font

#### Line Height
- `.leading-tight` (1.2)
- `.leading-snug` (1.3)
- `.leading-normal` (1.5)
- `.leading-relaxed` (1.75)
- `.leading-loose` (2)

#### Letter Spacing
- `.tracking-tighter` (-0.05em)
- `.tracking-tight` (-0.02em)
- `.tracking-normal` (0)
- `.tracking-wide` (0.05em)
- `.tracking-wider` (0.1em)
- `.tracking-widest` (0.15em)

#### Text Transform
- `.uppercase`
- `.lowercase`
- `.capitalize`

#### Text Alignment
- `.text-left`
- `.text-center`
- `.text-right`
- `.text-justify`

#### Text Colors
- `.text-primary`
- `.text-secondary`
- `.text-tertiary`
- `.text-muted`
- `.text-success`
- `.text-warning`
- `.text-danger`
- `.text-info`

#### Background Colors
- `.bg-primary`
- `.bg-secondary`
- `.bg-white`
- `.bg-inverse`

## Component Classes

### Page Title
```html
<div class="page-title">
  <h1>Page Title</h1>
  <p class="page-subtitle">Subtitle text</p>
</div>
```

### Card Title
```html
<div class="card">
  <div class="card-header">
    <h2 class="card-title">Card Title</h2>
  </div>
</div>
```

### Metric Title & Value
```html
<div class="metric-title">Total Users</div>
<div class="metric-value">1,234</div>
```

### Section Title
```html
<h3 class="section-title">Section Title</h3>
```

### Label Styles
```html
<div class="text-label">LABEL TEXT</div>
<div class="text-label-sm">Small Label</div>
<div class="text-label-lg">Large Label</div>
```

### Caption & Hint
```html
<div class="text-caption">Caption text</div>
<div class="text-hint">Hint text</div>
```

## Form Elements

### Form Label
```html
<label class="form-label">Field Label</label>
```

### Form Input
```html
<input type="text" class="form-input" placeholder="Enter text">
```

### Form Select
```html
<select class="form-select">
  <option>Option 1</option>
</select>
```

### Form Hint
```html
<p class="form-hint">Help text for this field</p>
```

### Form Error
```html
<p class="form-error">Error message</p>
```

## Table Elements

### Table Header
```html
<th class="table-header">Header Text</th>
```

### Table Cell
```html
<td class="table-cell">Cell Content</td>
```

## Navigation

### Nav Link
```html
<a href="#" class="nav-link">Navigation Item</a>
```

### Nav Section
```html
<div class="nav-section">Section Name</div>
```

## Buttons

### Button Sizes
```html
<button class="btn btn-primary">Default Button</button>
<button class="btn btn-primary btn-sm">Small Button</button>
<button class="btn btn-primary btn-lg">Large Button</button>
```

## Responsive Typography

The design system automatically adjusts typography on smaller screens:

- **Tablet (≤768px)**: Smaller heading and body sizes
- **Mobile (≤480px)**: Further reduced sizes for better readability

## Best Practices

1. **Always use the design system CSS** - Include `design-system.css` in all admin pages
2. **Use semantic classes** - Prefer `.text-h1`, `.text-body` over inline styles
3. **Don't override typography** - If you need custom styles, add them to the page-specific `<style>` block
4. **Test on mobile** - Typography is responsive, but verify it looks good on all devices
5. **Update tokens, not individual elements** - To change all headings, modify `--type-h1-size` in the design system

## Migration Guide

To migrate an existing page to use the design system:

1. Add `<link href="includes/design-system.css" rel="stylesheet">` to the `<head>`
2. Replace inline font styles with utility classes:
   - `font-size: 28px; font-weight: 600;` → `class="text-h1"`
   - `font-size: 14px; font-weight: 400;` → `class="text-body"`
   - `color: #0F1B2D;` → `class="text-primary"`
3. Remove duplicate CSS variables from page-specific styles

## File Structure

```
admin/
├── includes/
│   ├── design-system.css    # Main design system file
│   ├── form-styles.css      # Form-specific styles (existing)
│   ├── config.php           # Configuration file
│   └── DESGIN_SYSTEM_README.md  # This file
```

## Support

For questions or issues with the design system, contact the development team.