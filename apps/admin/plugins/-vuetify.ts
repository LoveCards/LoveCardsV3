// import this after install `@mdi/font` package
import "@mdi/font/css/materialdesignicons.css";

import "vuetify/styles";
import { createVuetify, type ThemeDefinition } from "vuetify";

//主题
const indigoTheme: ThemeDefinition = {
    dark: false,
    colors: {
        primary: "#3F51B5", // Indigo Blue
        secondary: "#FFC107", // Vibrant Yellow
        accent: "#FF4081", // Pink
        error: "#F44336", // Red
        info: "#2196F3", // Blue
        success: "#43A047", // Green
        warning: "#FF9800", // Orange
    },
};

export default defineNuxtPlugin((app) => {
    const vuetify = createVuetify({
        theme: {
            defaultTheme: "indigoTheme",
            themes: { indigoTheme },
        },
    });
    app.vueApp.use(vuetify);
});
