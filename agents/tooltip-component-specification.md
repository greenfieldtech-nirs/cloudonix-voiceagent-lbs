# Tooltip Component

A reusable tooltip component that displays help information on hover/focus for form elements and UI controls.

## Features

### 🎯 Core Functionality
- **Hover & Focus**: Shows tooltip on mouse hover and keyboard focus
- **Accessibility**: Proper ARIA labels and keyboard navigation support
- **Positioning**: Positioned above the trigger element with arrow pointer
- **Auto-hide**: Automatically hides after mouse/focus leaves with small delay

### 🎨 Visual Design
- **Icon**: Info icon (ⓘ) as the trigger element
- **Styling**: Dark background with white text for good contrast
- **Arrow**: CSS triangle arrow pointing to the trigger element
- **Z-index**: High z-index to appear above other content

## Usage

```typescript
import { Tooltip } from '../components/Tooltip';

// Basic usage
<Tooltip content="This is helpful information about this field." />

// With custom className
<Tooltip
  content="Detailed explanation of what this field does."
  className="ml-2"
/>
```

## Implementation Details

### Positioning
- **Placement**: Above the trigger element (bottom-full)
- **Alignment**: Centered horizontally relative to trigger
- **Offset**: 8px gap between tooltip and trigger

### Accessibility
- **ARIA**: `role="tooltip"` for screen readers
- **Focus**: Keyboard focus triggers tooltip display
- **Delay**: 150ms delay on hide to prevent flickering

### Styling
- **Background**: `bg-gray-900` (dark gray)
- **Text**: `text-white` for high contrast
- **Border**: Rounded corners with shadow
- **Arrow**: CSS border triangle pointing down

## Integration

### Settings Page
The tooltip component has been integrated into the Settings page to replace the "Configuration Help" section:

- **Cloudonix Domain**: "Contact Cloudonix support to get your account domain name or UUID."
- **API Key**: "Generate an API key from your Cloudonix dashboard under API settings."
- **Application Endpoint**: "This should point to your voice webhook handler endpoint where Cloudonix will send voice events."

## Technical Details

### Dependencies
- **React**: useState, useRef for state management
- **Lucide React**: Info icon from lucide-react library

### Performance
- **Lightweight**: Minimal DOM elements and efficient event handling
- **Cleanup**: Proper timeout cleanup to prevent memory leaks

### Browser Support
- **Modern Browsers**: Full support for CSS positioning and transitions
- **CSS Grid/Flexbox**: Not required, uses absolute positioning

## Files

- `src/components/Tooltip.tsx` - Main tooltip component
- `src/pages/Settings.tsx` - Integration example with form field tooltips

## Future Enhancements
- **Position Options**: Support for left, right, top, bottom positioning
- **Size Variants**: Different sizes for different contexts
- **Rich Content**: Support for HTML content and links
- **Animation**: Smooth fade-in/out animations