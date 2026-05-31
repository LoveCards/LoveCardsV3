import instance from "../../axios";

const APP_PATH = "/all/theme";

const getThemes = () => {
    return instance.get(`${APP_PATH}/list`);
};

const uploadTheme = (file: File) => {
    const formData = new FormData();
    formData.append("file", file);
    return instance.post(`${APP_PATH}/upload`, formData, {
        headers: { "Content-Type": "multipart/form-data" },
    });
};

const activateTheme = (name: string) => {
    return instance.post(`${APP_PATH}/activate`, { name });
};

const getThemeConfig = () => {
    return instance.get(`${APP_PATH}/config`);
};

const updateThemeConfig = (config: Record<string, any>) => {
    return instance.put(`${APP_PATH}/config`, config);
};

const freezeThemeConfig = () => {
    return instance.post(`${APP_PATH}/freeze`);
};

const deleteTheme = (name: string) => {
    return instance.delete(`${APP_PATH}/delete`, { data: { name } });
};

const ThemeApi = {
    getThemes,
    uploadTheme,
    activateTheme,
    getThemeConfig,
    updateThemeConfig,
    freezeThemeConfig,
    deleteTheme,
};

export default ThemeApi;
