import type { ComponentStory, ComponentMeta } from '@storybook/react';
import { SocialSigninButton } from './social-signin-button';

const Story: ComponentMeta<typeof SocialSigninButton> = {
  component: SocialSigninButton,
  title: 'SocialSigninButton',
};
export default Story;

const Template: ComponentStory<typeof SocialSigninButton> = (args) => <SocialSigninButton {...args} />;

export const Primary = Template.bind({});
Primary.args = {};
