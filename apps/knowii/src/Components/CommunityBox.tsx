import { Divider } from 'primereact/divider';
import { Card } from 'primereact/card';
import { Community } from '@knowii/common';
import classNames from 'classnames';
import { FaPlus } from 'react-icons/fa';

interface Props {
  community: Community;
  creationMode: boolean;
  clickHandler?: () => void;
}

export default function CommunityBox(props: Props) {
  return props.creationMode ? (
    // eslint-disable-next-line @typescript-eslint/no-empty-function
    <div
      className="w-[300px] h-[150px] rounded-md flex flex-col justify-center items-center outline-dashed outline-2 outline-offset-[-2px] text-gray-800 hover:text-primary-500 hover:cursor-pointer hover:outline-4 hover:outline-primary-500 hover:outline-offset-[0px] hover:rounded-md"
      onClick={
        props.clickHandler
          ? props.clickHandler
          : // eslint-disable-next-line @typescript-eslint/no-empty-function
            () => {}
      }
    >
      <FaPlus className="text-3xl" />
      <span className="text-xl font-bold">New</span>
    </div>
  ) : (
    <div className="w-[300px] h-[150px] rounded-lg hover:outline hover:outline-primary-500 hover:outline-offset-2 hover:rounded-md shadow-lg overflow-hidden flex-shrink-0">
      <Card
        title={<span className={classNames('text-[1.2rem] text-primary-500 leading-none ')}>{props.community.name}</span>}
        subTitle={<Divider className={classNames('p-0 m-0')} />}
        className={classNames('')}
      >
        <p className={classNames('text-sm text-gray-800 text-ellipsis line-clamp-1 sm:line-clamp-2')}>{props.community.description}</p>
      </Card>
    </div>
  );
}
