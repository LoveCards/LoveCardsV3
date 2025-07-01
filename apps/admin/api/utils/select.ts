const User = {
    status: [
        { title: "#0 正常", value: 0 },
        { title: "#1 封禁", value: 1 },
        { title: "#2 冻结", value: 2 },
    ]
}

const Cards = {
    model: [
        { title: "#0 表白卡", value: 0 },
        { title: "#1 交流卡", value: 1 }
    ],
    top: [
        { title: "#0 正常", value: 0 },
        { title: "#1 封禁", value: 1 }
    ],
    status: [
        { title: "#0 正常", value: 0 },
        { title: "#1 封禁", value: 1 },
        { title: "#2 隐藏", value: 2 }
    ]
}

const SelectUtils = {
    User,
    Cards
}

export default SelectUtils;