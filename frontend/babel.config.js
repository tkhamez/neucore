module.exports = {
    "presets": [
        ["@babel/preset-env", {
            "useBuiltIns": "entry",
            "corejs": "3.0.0",
            "modules": false,
            "targets": "> 0.5%, last 2 versions, Firefox ESR, ie 11, not dead"
        }]
    ]
};
