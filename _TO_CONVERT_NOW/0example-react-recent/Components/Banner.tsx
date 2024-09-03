import React, { useEffect, useState } from 'react';
import { usePage } from '@inertiajs/react';

// Might break

const Banner: React.FC = () => {
  const { props }: any = usePage();
  const [show, setShow] = useState(true);
  const [style, setStyle] = useState('success');
  const [message, setMessage] = useState('');

  useEffect(() => {
    setStyle(props.jetstream.flash?.bannerStyle || 'success');
    setMessage(props.jetstream.flash?.banner || '');
    setShow(true);
  }, [props]);

  if (!show || !message) {
    return null;
  }

  return (
    <div className={style === 'success' ? 'bg-indigo-500' : 'bg-red-700'}>
      <div className="max-w-screen-xl px-3 py-2 mx-auto sm:px-6 lg:px-8">
        <div className="flex flex-wrap items-center justify-between">
          <div className="flex items-center flex-1 w-0 min-w-0">
            <span className={`flex p-2 rounded-lg ${style === 'success' ? 'bg-indigo-600' : 'bg-red-600'}`}>
              {style === 'success' ? (
                <svg
                  className="w-5 h-5 text-white"
                  xmlns="http://www.w3.org/2000/svg"
                  fill="none"
                  viewBox="0 0 24 24"
                  strokeWidth="1.5"
                  stroke="currentColor"
                >
                  <path strokeLinecap="round" strokeLinejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              ) : (
                <svg
                  className="w-5 h-5 text-white"
                  xmlns="http://www.w3.org/2000/svg"
                  fill="none"
                  viewBox="0 0 24 24"
                  strokeWidth="1.5"
                  stroke="currentColor"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"
                  />
                </svg>
              )}
            </span>

            <p className="text-sm font-medium text-white truncate ms-3">{message}</p>
          </div>

          <div className="shrink-0 sm:ms-3">
            <button
              type="button"
              className={`-me-1 flex p-2 rounded-md focus:outline-none sm:-me-2 transition ${
                style === 'success' ? 'hover:bg-indigo-600 focus:bg-indigo-600' : 'hover:bg-red-600 focus:bg-red-600'
              }`}
              aria-label="Dismiss"
              onClick={() => setShow(false)}
            >
              <svg
                className="w-5 h-5 text-white"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                strokeWidth="1.5"
                stroke="currentColor"
              >
                <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};

export default Banner;
