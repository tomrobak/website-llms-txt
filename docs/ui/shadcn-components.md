# Shadcn-Inspired UI Components

## Overview
The plugin uses a complete shadcn-inspired design system for modern, professional UI components consistent with contemporary web design standards.

## Design System

### Color Palette
```css
:root {
  --llms-primary: #3b82f6;      /* Primary blue */
  --llms-primary-hover: #2563eb; /* Darker blue on hover */
  --llms-secondary: #f8fafc;     /* Light gray background */
  --llms-text: #0f172a;          /* Dark text */
  --llms-text-muted: #64748b;    /* Muted text */
  --llms-border: #e2e8f0;        /* Border color */
  --llms-success: #10b981;       /* Success green */
  --llms-error: #ef4444;         /* Error red */
  --llms-warning: #f59e0b;       /* Warning orange */
}
```

### Typography
- **Font Stack**: System fonts (-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto)
- **Weights**: 400 (normal), 500 (medium), 600 (semibold), 700 (bold)
- **Sizes**: Consistent scale with rem units

## Component Library

### 1. Cards (`.llms-card`)
Modern card components with:
- Subtle shadows and borders
- Hover effects
- Header/content separation
- Rounded corners (6px radius)

### 2. Buttons (`.llms-button`)
Multiple button variants:
- **Primary**: Blue background, white text
- **Secondary**: White background, bordered
- **Small**: Reduced padding for compact layouts

### 3. Form Controls
- **Inputs**: Consistent padding, focus states, border radius
- **Checkboxes**: Custom styled with smooth animations
- **Select**: Styled dropdowns matching the design system

### 4. Checkboxes (`.llms-checkbox-wrapper`)
Modern checkbox implementation:
- Native `input[type="checkbox"]` with `appearance: none`
- Custom styling with checkmark animation
- Proper focus states and accessibility
- Click-to-toggle on label

### 5. Alerts (`.llms-alert`)
Status indicators for:
- Success (green)
- Error (red) 
- Warning (orange)
- Info (blue)

### 6. Tables (`.llms-table`)
Clean data tables with:
- Bordered layout
- Header styling
- Consistent spacing

### 7. Badges (`.llms-badge`)
Small status indicators with:
- Rounded pill shape
- Color-coded variants
- Icon support

### 8. Grid System (`.llms-grid`)
Responsive grid layouts:
- 2-column and 3-column options
- Automatic mobile stacking
- Consistent gap spacing

## Layout Components

### Tab Navigation (`.llms-tabs`)
Clean tab interface:
- Horizontal tab bar
- Active state indicators
- Smooth transitions
- Responsive overflow handling

### Header (`.llms-header`)
Centered header with:
- Gradient title text
- Subtitle support
- Proper spacing

### Footer (`.llms-footer`)
Community section with:
- Centered content
- Button groups
- Background styling

## Accessibility Features

### Focus Management
- Visible focus indicators
- Keyboard navigation support
- Proper tab order

### Color Contrast
- WCAG AA compliant colors
- Clear visual hierarchy
- Sufficient contrast ratios

### Screen Reader Support
- Semantic HTML structure
- Proper labeling
- Form field associations

## Responsive Design

### Breakpoints
- Mobile: `max-width: 768px`
- Automatic grid stacking
- Flexible button layouts
- Overflow handling for tabs

### Mobile Optimizations
- Touch-friendly target sizes
- Simplified navigation
- Reduced padding on small screens

## Dark Mode Support
CSS variables enable automatic dark mode:
- System preference detection
- Consistent color inversion
- Maintained contrast ratios

## Implementation Example

```html
<div class="llms-card">
  <div class="llms-card-header">
    <h2 class="llms-card-title">Card Title</h2>
    <p class="llms-card-description">Card description</p>
  </div>
  <div class="llms-card-content">
    <div class="llms-checkbox-wrapper">
      <input type="checkbox" id="example">
      <label for="example">Checkbox Label</label>
    </div>
    <button class="llms-button primary">Save</button>
  </div>
</div>
```

This design system ensures consistency across all plugin interfaces while providing a modern, professional user experience. 