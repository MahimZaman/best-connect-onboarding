(function () {
  "use strict";

  let loadingPromise = null;

  window.BCOBPDFJS = {
    load() {
      if (
        window.pdfjsLib &&
        typeof window.pdfjsLib.getDocument === "function"
      ) {
        return Promise.resolve(window.pdfjsLib);
      }

      if (loadingPromise) {
        return loadingPromise;
      }

      const config = window.BCOBPdfJs || {};

      if (!config.module_url || !config.worker_url) {
        return Promise.reject(new Error("PDF.js configuration is missing."));
      }

      loadingPromise = import(config.module_url)
        .then((pdfjsLib) => {
          pdfjsLib.GlobalWorkerOptions.workerSrc = config.worker_url;

          window.pdfjsLib = pdfjsLib;

          return pdfjsLib;
        })
        .catch((error) => {
          loadingPromise = null;

          throw error;
        });

      return loadingPromise;
    },
  };
})();
