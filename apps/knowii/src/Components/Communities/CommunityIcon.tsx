import { GrGroup } from 'react-icons/gr';
import { CommunityVisibility, communityVisibilityOptions } from '@knowii/common';

interface Props {
  visibility: CommunityVisibility | null;
}

const CommunityIcon = (props: Props) => {
  let color = '';

  if (props.visibility) {
    const matchedVisibility = communityVisibilityOptions.find((item) => item.visibility === props.visibility);
    color = matchedVisibility ? matchedVisibility.color : '';
  }

  return <GrGroup className={color} />;
};

export default CommunityIcon;
