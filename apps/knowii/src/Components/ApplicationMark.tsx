interface Props {
  className?: string;
  viewBox?: string;
  bottomLeftColor?: string;
  bottomRightColor?: string;
  topLeftColor?: string;
  topRightColor?: string;
}

export default function ApplicationMark(props: Props) {
  return (
    <svg viewBox={props.viewBox ? props.viewBox : '0 0 512 512'} fill="none" className={props.className} xmlns="http://www.w3.org/2000/svg">
      {/* Top left */}
      <path
        fillRule="evenodd"
        clipRule="evenodd"
        d="M137.172 129.472C158.07 107.191 182.03 92.2333 209.011 84.5357C227.523 79.2504 240 62.2626 240 43.3309C240 14.625 211.937 -6.30531 183.867 1.73579C162.507 7.84078 141.975 16.8421 122.294 28.7711C81.7622 53.3159 49.6837 86.0815 26.0502 127.06C15.9193 144.633 7.98313 162.644 2.20994 181.084C-7.39613 211.811 15.4819 243 48.2205 243C70.4067 243 89.8256 228.159 95.0183 206.987C102.207 177.68 116.218 151.823 137.172 129.472Z"
        fill={props.topLeftColor ? props.topLeftColor : '#E5007D'}
      />
      {/* Top right */}
      <path
        fillRule="evenodd"
        clipRule="evenodd"
        d="M340.465 101.516C366.124 117.207 386.407 138.67 401.306 165.891C408.493 179.032 413.941 192.83 417.658 207.285C423.034 228.256 442.07 243 464.021 243C496.066 243 519.2 212.549 509.94 182.333C507.279 173.658 504.142 165.085 500.521 156.606C486.83 124.544 467.833 96.1344 443.548 71.3622C419.262 46.59 391.014 27.0741 358.81 12.8225C349.137 8.55169 339.29 4.91446 329.275 1.93429C300.812 -6.55256 272 13.9176 272 43.2109V43.4377C272 62.4921 284.667 79.2233 303.163 84.7769C316.1 88.6566 328.537 94.2259 340.465 101.516Z"
        fill={props.topRightColor ? props.topRightColor : '#E5007D'}
      />
      {/* Bottom left */}
      <path
        fillRule="evenodd"
        clipRule="evenodd"
        d="M137.111 379.943C116.837 358.057 103.016 332.976 95.6087 304.754C90.0787 283.705 70.7358 269 48.5922 269C15.6273 269 -7.43138 300.394 2.21222 331.343C7.97291 349.853 15.874 367.879 25.9234 385.412C49.5709 426.675 81.6684 459.475 122.224 483.791C141.527 495.37 161.642 504.16 182.56 510.208C211.276 518.506 240 496.917 240 467.523C240 448.154 227.253 430.855 208.372 425.307C181.621 417.455 157.854 402.352 137.111 379.943Z"
        fill={props.bottomLeftColor ? props.bottomLeftColor : '#FFFFFF'}
      />
      {/* Bottom right */}
      <path
        fillRule="evenodd"
        clipRule="evenodd"
        d="M402.332 341.044C387.347 369.463 366.651 391.787 340.259 408.048C328.615 415.229 316.517 420.745 303.968 424.652C285.017 430.544 272 447.648 272 467.222C272 496.79 300.748 518.489 329.566 510.229C350.604 504.213 370.736 495.453 389.941 483.928C430.365 459.697 462.286 427.113 485.679 386.184C496.229 367.744 504.376 348.639 510.159 328.862C518.94 298.81 495.158 269 463.404 269C442.327 269 424.035 282.502 417.467 302.014C413.009 315.258 408.86 328.667 402.332 341.044Z"
        fill={props.bottomRightColor ? props.bottomRightColor : '#E5007D'}
      />
    </svg>
  );
}
