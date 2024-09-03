import { useEffect, useState, ReactNode } from 'react';

interface MyComponentProps {
  on: boolean;
  children: ReactNode;
  className?: string;
}

const MyComponent: React.FC<MyComponentProps> = ({ on, children, className }) => {
  const [visible, setVisible] = useState(on);

  useEffect(() => {
    setVisible(on);
  }, [on]);

  return (
    <div className={className}>
      <div
        className={`transition ease-in duration-1000 ${visible ? 'opacity-100' : 'opacity-0'} text-sm text-gray-600 dark:text-gray-400`}
        style={{ display: visible ? 'block' : 'none' }}
      >
        {children}
      </div>
    </div>
  );
};

export default MyComponent;
