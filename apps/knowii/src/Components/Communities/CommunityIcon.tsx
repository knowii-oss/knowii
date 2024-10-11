import { GrGroup } from 'react-icons/gr';
import { CommunityVisibility } from '@knowii/common';

interface Props {
  visibility: CommunityVisibility | null;
}

const CommunityIcon = (props: Props) => {
  let color = '';

  if (props.visibility) {
    switch (props.visibility) {
      case 'public':
        color = 'text-green-500';
        break;
      case 'private':
        color = 'text-red-500';
        break;
      case 'personal':
        color = 'text-gray-500';
        break;
      default:
        color = 'text-gray-500';
    }
  }

  return <GrGroup className={color} />;
};

export default CommunityIcon;
