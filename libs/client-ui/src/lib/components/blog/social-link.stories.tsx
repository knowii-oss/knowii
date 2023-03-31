import type { ComponentMeta, ComponentStory } from '@storybook/react';
import { FaTwitter } from 'react-icons/fa';
import { SocialLink } from './social-link';

const Story: ComponentMeta<typeof SocialLink> = {
  component: SocialLink,
  title: 'SocialLink',
};
export default Story;

const Template: ComponentStory<typeof SocialLink> = (args) => <SocialLink {...args} />;

export const Primary = Template.bind({});
Primary.args = {
  url: 'https://twitter.com/dSebastien',
  name: 'Twitter',
  icon: <FaTwitter className="inline hover:text-blue-500 hover:bg-white text-gray-100" />,
  hideText: true,
  ariaLabelText: 'Go to my Twitter profile',
  titleText: 'Go to my Twitter profile',
};
