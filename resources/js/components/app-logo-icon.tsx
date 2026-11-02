import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            viewBox="0 0 32 32"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
        >
            <defs>
                <linearGradient
                    id="eventflow-mark"
                    x1="0"
                    y1="0"
                    x2="32"
                    y2="32"
                    gradientUnits="userSpaceOnUse"
                >
                    <stop offset="0" stopColor="currentColor" />
                    <stop
                        offset="1"
                        stopColor="currentColor"
                        stopOpacity="0.6"
                    />
                </linearGradient>
            </defs>
            <path
                d="M3 20C7 14 11 14 15 20C19 26 23 26 27 20"
                stroke="url(#eventflow-mark)"
                strokeWidth="3"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
            <path
                d="M3 12C7 6 11 6 15 12C19 18 23 18 27 12"
                stroke="url(#eventflow-mark)"
                strokeWidth="3"
                strokeLinecap="round"
                strokeLinejoin="round"
                opacity="0.55"
            />
        </svg>
    );
}
