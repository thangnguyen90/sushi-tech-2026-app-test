export const loadScript = (src: string) => {
    return new Promise((resolve, reject) => {
        if (document.querySelector(`script[src="${src}"]`)) {
            return resolve(true)
        }

        const script = document.createElement('script')
        script.src = src
        script.onload = resolve
        script.onerror = reject

        document.body.appendChild(script)
    })
}

export const unloadScript = (src: string) => {
    document.querySelectorAll(`script[src="${src}"]`).forEach((script) => script.remove())
}
