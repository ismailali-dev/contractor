const config = {
  path: {
    html: ['./', './docs'],
    scss: 'src/scss',
    src_js: 'src/js',
    js: 'assets/js',
    css: 'assets/css',
    img: 'assets/img',
    fonts: 'assets/fonts',
    app_icons: 'assets/app-icons',
    vendor: 'assets/vendor',
    json: 'assets/json',
    dist: 'dist',
  },
  icons: {
    src: 'src/icons',
    output: 'assets/icons',
    fontName: 'finder-icons',
    cssPrefix: 'fi',
  },
  fileNames: {
    css: 'theme',
    js: 'theme',
  },
  jsBanner: `
  /*!
   * Finder | Directory & Listings Bootstrap HTML Template
   * Copyright 2024 Coderthemes
   * Theme scripts
   *
   * @copyright Coderthemes
   * @version 2.0.0
   */
  `,
}

export default config
