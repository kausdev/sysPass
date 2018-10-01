/*
 * sysPass
 *
 * @author nuxsmin
 * @link https://syspass.org
 * @copyright 2012-2018, Rubén Domínguez nuxsmin@$syspass.org
 *
 * This file is part of sysPass.
 *
 * sysPass is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * sysPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 *  along with sysPass.  If not, see <http://www.gnu.org/licenses/>.
 */

sysPass.Util = function (log) {
    "use strict";

    /**
     * @author http://stackoverflow.com/users/24950/robert-k
     * @link http://stackoverflow.com/questions/5796718/html-entity-decode
     */
    const decodeEntities = function () {
        // this prevents any overhead from creating the object each time
        const element = document.createElement("div");

        function decodeHTMLEntities(str) {
            if (str && typeof str === "string") {
                // strip script/html tags
                str = str.replace(/<script[^>]*>([\S\s]*?)<\/script>/gmi, "");
                str = str.replace(/<\/?\w(?:[^"'>]|"[^"]*"|'[^']*')*>/gmi, "");
                element.innerHTML = str;
                str = element.textContent;
                element.textContent = "";
            }

            return str;
        }

        return decodeHTMLEntities;
    };

    /**
     * Resizes an image to viewport size
     *
     * @param $obj
     */
    const resizeImage = function ($obj) {
        const viewport = {
            width: $(window).width() * 0.90,
            height: $(window).height() * 0.90
        };

        const image = {
            width: $obj.width(),
            height: $obj.height()
        };

        const dimension = {
            calc: 0,
            main: 0,
            secondary: 0,
            factor: 0.90,
            rel: image.width / image.height
        };

        /**
         * Fits the image aspect ratio
         *
         * It takes into account the maximum dimension in the opposite axis
         *
         * @param dimension
         * @returns {*}
         */
        const adjustRel = function (dimension) {
            if (dimension.main > dimension.secondary) {
                dimension.calc = dimension.main / dimension.rel;
            } else if (dimension.main < dimension.secondary) {
                dimension.calc = dimension.main * dimension.rel;
            }

            if (dimension.calc > dimension.secondary) {
                dimension.main *= dimension.factor;

                adjustRel(dimension);
            }

            return dimension;
        };

        /**
         * Resize from width
         */
        const resizeWidth = function () {
            dimension.main = viewport.width;
            dimension.secondary = viewport.height;

            const adjust = adjustRel(dimension);

            $obj.css({
                "width": adjust.main,
                "height": adjust.calc
            });

            image.width = adjust.main;
            image.height = adjust.calc;
        };

        /**
         * Resize from height
         */
        const resizeHeight = function () {
            dimension.main = viewport.height;
            dimension.secondary = viewport.width;

            const adjust = adjustRel(dimension);

            $obj.css({
                "width": adjust.calc,
                "height": adjust.main
            });

            image.width = adjust.calc;
            image.height = adjust.main;
        };

        if (image.width > viewport.width) {
            resizeWidth();
        } else if (image.height > viewport.height) {
            resizeHeight();
        }

        return image;
    };

    /**
     * Function to enable file uploading through a drag&drop or form
     * @param $obj
     * @returns {{requestDoneAction: string, setRequestData: setRequestData, getRequestData: function(): {actionId: *, itemId: *, sk: *}, beforeSendAction: string, url: string, allowedExts: Array}}
     */
    const fileUpload = function ($obj) {

        /**
         * Initializes the files form in legacy mode
         *
         * @param display
         * @returns {*}
         */
        const initForm = function (display) {
            const $form = $("#fileUploadForm");

            if (display === false) {
                $form.hide();
            }

            const $input = $form.find("input[type='file']");

            $input.on("change", function () {
                if (typeof options.beforeSendAction === "function") {
                    options.beforeSendAction();
                }

                handleFiles(this.files);
            });

            return $input;
        };

        const requestData = {
            actionId: $obj.data("action-id"),
            itemId: $obj.data("item-id"),
            sk: sysPassApp.sk.get()
        };

        const options = {
            requestDoneAction: "",
            setRequestData: function (data) {
                $.extend(requestData, data);
            },
            getRequestData: function () {
                return requestData;
            },
            beforeSendAction: "",
            url: "",
            allowedExts: []
        };

        /**
         * Uploads a file
         * @param file
         * @returns {boolean}
         */
        const sendFile = function (file) {
            if (options.url === undefined || options.url === "") {
                return false;
            }

            // Objeto FormData para crear datos de un formulario
            const fd = new FormData();
            fd.append("inFile", file);
            fd.append("isAjax", 1);

            requestData.sk = sysPassApp.sk.get();

            Object.keys(requestData).forEach(function (key) {
                fd.append(key, requestData[key]);
            });

            const opts = sysPassApp.requests.getRequestOpts();
            opts.url = options.url;
            opts.processData = false;
            opts.contentType = false;
            opts.data = fd;

            sysPassApp.requests.getActionCall(opts, function (json) {
                const status = json.status;
                const description = json.description;

                if (status === 0) {
                    if (typeof options.requestDoneAction === "function") {
                        options.requestDoneAction();
                    }

                    sysPassApp.msg.ok(description);
                } else if (status === 10) {
                    sysPassApp.appActions().main.logout();
                } else {
                    sysPassApp.msg.error(description);
                }
            });

        };

        const checkFileSize = function (size) {
            return (size / 1000 > sysPassApp.config.FILES.MAX_SIZE);
        };

        const checkFileExtension = function (name) {
            for (let ext in options.allowedExts) {
                if (name.indexOf(options.allowedExts[ext]) !== -1) {
                    return true;
                }
            }

            return false;
        };

        /**
         * Checks the files and upload them
         */
        const handleFiles = function (filesArray) {
            if (filesArray.length > 5) {
                sysPassApp.msg.error(sysPassApp.config.LANG[17] + " (Max: 5)");
                return;
            }

            for (let i = 0; i < filesArray.length; i++) {
                const file = filesArray[i];
                if (checkFileSize(file.size)) {
                    sysPassApp.msg.error(sysPassApp.config.LANG[18] + "<br>" + file.name + " (Max: " + sysPassApp.config.FILES.MAX_SIZE + ")");
                } else if (!checkFileExtension(file.name.toUpperCase())) {
                    sysPassApp.msg.error(sysPassApp.config.LANG[19] + "<br>" + file.name);
                } else {
                    sendFile(filesArray[i]);
                }
            }
        };

        /**
         * Initializes the Drag&Drop zone
         */
        const init = function () {
            log.info("fileUpload:init");

            const fallback = initForm(false);

            $obj.on("dragover dragenter", function (e) {
                log.info("fileUpload:drag");

                e.stopPropagation();
                e.preventDefault();
            });

            $obj.on("drop", function (e) {
                log.info("fileUpload:drop");

                e.stopPropagation();
                e.preventDefault();

                if (typeof options.beforeSendAction === "function") {
                    options.beforeSendAction();
                }

                handleFiles(e.originalEvent.dataTransfer.files);
            });

            $obj.on("click", function () {
                fallback.click();
            });
        };


        if (window.File && window.FileList && window.FileReader) {
            init();
        } else {
            initForm(true);
        }

        return options;
    };

    /**
     *
     * @type {{md5: function(*=): String}}
     */
    const hash = {
        md5: function (data) {
            return SparkMD5.hash(data, false);
        }
    };

    /**
     * Scrolls to the top of the viewport
     */
    const scrollUp = function () {
        $("html, body").animate({scrollTop: 0}, "slow");
    };

    // Función para establecer la altura del contenedor ajax
    const setContentSize = function () {
        const $container = $("#container");

        if ($container.hasClass("content-no-auto-resize")) {
            return;
        }

        //console.info($("#content").height());

        // Calculate total height for full body resize
        $container.css("height", $("#content").height() + 200);
    };

    // Función para obtener el tiempo actual en milisegundos
    const getTime = function () {
        const t = new Date();
        return t.getTime();
    };

    /**
     *
     * @type {{config: {passLength: number, minPasswordLength: number, complexity: {chars: boolean, numbers: boolean, symbols: boolean, uppercase: boolean, numlength: number}}, random: random, output: output, checkLevel: checkLevel}}
     */
    const password = {
        config: {
            passLength: 0,
            minPasswordLength: 8,
            complexity: {
                chars: true,
                numbers: true,
                symbols: true,
                uppercase: true,
                numlength: 12
            }
        },
        /**
         * Function to generate random password and call a callback sending the generated string
         * and a zxcvbn object
         *
         * @param callback
         */
        random: function (callback) {
            log.info("password:random");

            let i = 0;
            let chars = "";
            let password = "";

            const getRandomChar = function (min, max) {
                return chars.charAt(Math.floor((Math.random() * max) + min));
            };

            if (this.config.complexity.symbols) {
                chars += "!\"\\·@|#$~%&/()=?'¿¡^*[]·;,_-{}<>";
            }

            if (this.config.complexity.numbers) {
                chars += "1234567890";
            }

            if (this.config.complexity.chars) {
                chars += "abcdefghijklmnopqrstuvwxyz";

                if (this.config.complexity.uppercase) {
                    chars += String("abcdefghijklmnopqrstuvwxyz").toUpperCase();
                }
            }

            for (; i++ < this.config.complexity.numlength;) {
                password += getRandomChar(0, chars.length - 1);
            }

            this.config.passLength = password.length;

            if (typeof callback === "function") {
                callback(password, zxcvbn(password));
            }
        },
        output: function (level, $target) {
            log.info("password:outputResult");

            const $passLevel = $("#password-level-" + $target.attr("id"));
            const score = level.score;

            $passLevel.removeClass("weak good strong strongest");

            if (this.config.passLength === 0) {
                $passLevel.attr("data-level-msg", "");
            } else if (this.config.passLength < this.config.minPasswordLength) {
                $passLevel.attr("data-level-msg", sysPassApp.config.LANG[11]).addClass("weak");
            } else if (score === 0) {
                $passLevel.attr("data-level-msg", sysPassApp.config.LANG[9] + " - " + level.feedback.warning).addClass("weak");
            } else if (score === 1 || score === 2) {
                $passLevel.attr("data-level-msg", sysPassApp.config.LANG[8] + " - " + level.feedback.warning).addClass("good");
            } else if (score === 3) {
                $passLevel.attr("data-level-msg", sysPassApp.config.LANG[7]).addClass("strong");
            } else if (score === 4) {
                $passLevel.attr("data-level-msg", sysPassApp.config.LANG[10]).addClass("strongest");
            }
        },
        checkLevel: function ($target) {
            log.info("password:checkPassLevel");

            this.config.passLength = $target.val().length;

            password.output(zxcvbn($target.val()), $target);
        }
    };

    /**
     * Redirect to a given URL
     *
     * @param url
     */
    const redirect = function (url) {
        window.location.replace(url);
    };

    /**
     * @see https://stackoverflow.com/questions/3231459/create-unique-id-with-javascript
     * @returns {string}
     */
    const uniqueId = function () {
        // always start with a letter (for DOM friendlyness)
        let idstr = String.fromCharCode(Math.floor((Math.random() * 25) + 65));

        do {
            // between numbers and characters (48 is 0 and 90 is Z (42-48 = 90)
            const ascicode = Math.floor((Math.random() * 42) + 48);
            if (ascicode < 58 || ascicode > 64) {
                // exclude all chars between : (58) and @ (64)
                idstr += String.fromCharCode(ascicode);
            }
        } while (idstr.length < 32);

        return idstr.toLowerCase();
    };

    return {
        decodeEntities: decodeEntities,
        resizeImage: resizeImage,
        fileUpload: fileUpload,
        scrollUp: scrollUp,
        setContentSize: setContentSize,
        redirect: redirect,
        uniqueId: uniqueId,
        password: password,
        hash: hash
    };
};