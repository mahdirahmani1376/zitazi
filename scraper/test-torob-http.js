async function fetchTorob() {
    const response = await fetch(
        "https://api.torob.com/v4/base-product/details/?prk=aef9eeca-6855-42d5-b253-2495062505d8",
        {
            method: "GET",
            headers: {
                "User-Agent":
                    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36",

                "Accept":
                    "application/json, text/plain, */*",

                "Accept-Language":
                    "en-US,en;q=0.9",

                "Accept-Encoding":
                    "gzip, deflate, br",

                "Cache-Control":
                    "no-cache",

                "Pragma":
                    "no-cache",

                "Referer":
                    "https://torob.com/",

                "Origin":
                    "https://torob.com/",

                "DNT": "1",

                "Upgrade-Insecure-Requests": "1",

                "Priority": "u=1, i"
            },
            redirect: "follow"
        }
    );

    console.log('response', await response.text())

}

(async () => {
    await fetchTorob();
})();
