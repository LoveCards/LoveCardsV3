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
        { title: "#1 置顶", value: 1 }
    ],
    status: [
        { title: "#0 正常", value: 0 },
        { title: "#1 封禁", value: 1 },
        { title: "#2 隐藏", value: 2 },
        { title: "#3 待审核", value: 3 }
    ]
}

const Common = {
    Table: {
        ListRowsOptions: [
            { title: "10 / 页", value: 10 },
            { title: "20 / 页", value: 20 },
            { title: "50 / 页", value: 50 },
            { title: "100 / 页", value: 100 },
        ]
    },

}

const SelectUtils = {
    User,
    Cards,
    Common
}

export default SelectUtils;