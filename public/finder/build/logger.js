// Define colors for formatting text in the console
const colors = {
  reset: '\x1b[0m',
  bold: '\x1b[1m',
  red: '\x1b[31m',
  green: '\x1b[32m',
  blue: '\x1b[34m',
  cyan: '\x1b[36m',
  blackBg: '\x1b[40m',
  purple: '\x1b[35m',
  yellow: '\x1b[33m',
}

// Configure a logger function with a specific context
const configureLogger = (context) => ({
  // Log an info message
  info: (message) =>
    console.log(
      colors.cyan + colors.blackBg,
      `${context}`,
      colors.reset,
      colors.blue + colors.bold,
      'Info',
      colors.reset,
      colors.purple,
      message,
      colors.reset
    ),
  // Log an error message
  error: (message) =>
    console.log(
      colors.cyan + colors.blackBg,
      `${context}`,
      colors.reset,
      colors.red + colors.bold,
      'Error!',
      colors.reset,
      colors.red,
      message,
      colors.reset
    ),
  // Log a success message
  success: (message) =>
    console.log(
      colors.cyan + colors.blackBg,
      `${context}`,
      colors.reset,
      colors.green + colors.bold,
      'Success',
      colors.reset,
      colors.purple,
      message,
      colors.reset
    ),
  // Log a warning message
  warning: (message) =>
    console.log(
      colors.cyan + colors.blackBg,
      `${context}`,
      colors.reset,
      colors.yellow + colors.bold,
      'Warn',
      colors.reset,
      message
    ),
})

export default configureLogger
