import * as lodash from 'lodash-es'

const jumpRoot = (path = '/') => {
    return window.location.href = path;
}

const CommonUtils = {
    lodash,
    jumpRoot
}

export default CommonUtils;