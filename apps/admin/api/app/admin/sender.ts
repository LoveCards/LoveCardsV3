import instance from "../../axios";

const getTypes = () => {
    return instance.get("/all/sender/types");
};

const getChannels = () => {
    return instance.get("/all/sender/channels");
};

const getMeta = (type: string) => {
    return instance.get(`/all/sender/${type}/meta`);
};

const testChannel = (channel: string, to?: string) => {
    return instance.post("/all/sender/test-channel", { channel, to }, { timeout: 60000 });
};

const getTemplates = () => {
    return instance.get("/all/sender/templates");
};

const install = () => {
    return instance.post("/all/sender/install");
};

const SenderApi = {
    getTypes,
    getChannels,
    getMeta,
    testChannel,
    getTemplates,
    install,
};

export default SenderApi;
